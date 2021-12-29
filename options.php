<?php
/*
Plugin Name: Import markdown structure to posts or pages
Plugin URI: https://tmy.io
Description: Able to import markdown syntax and automatically assign parent-child categories
Version: 1
Author: Thomas Kuhnert
Author URI: https://tmy.io
*/
include 'vendor/autoload.php';
@ini_set('display_errors', 1);
@ini_set('max_execution_time', '300'); //300 seconds = 5 minutes
include(plugin_dir_path(__FILE__) . 'class.pdf2text.php');
// $pdf2text = new PDF2Text();
// $a = filesize('/var/www/html/wp-content/uploads/2021/11/Emotional-Design-Why-We-Love-or-Hate-Everyday-Things-Donald-Norman.pdf');
// echo '<pre>$a';
// var_dump($a);
// echo '</pre>';

// $pdf    = $parser->parseFile('/var/www/html/wp-content/uploads/2021/11/Emotional-Design-Why-We-Love-or-Hate-Everyday-Things-Donald-Norman.pdf');
// $pages  = $pdf->getPages();
// echo '<pre>$pages';
// var_dump(array_slice($pages, 2));
// echo '</pre>';
// var_dump(PDFParser);

// global $pagenow;
// echo '<pre>26 $pagenow';
// var_dump($pagenow);
// echo '</pre>';
$max_file_download = get_option('tmy_open_ai_batch_size') ? get_option('tmy_open_ai_batch_size') : 5;
// if ($pagenow === 'options-general.php') {
//     $allposts = get_posts(array('post_type' => 'archive', 'numberposts' => -1));
//     foreach ($allposts as $eachpost) {
//         wp_delete_post($eachpost->ID, true);
//     }
//     $attachments = get_posts(array('post_type' => 'attachment', 'numberposts' => -1));
//     foreach ($attachments as $eachpost) {
//         // if ($eachpost->post_title !== "MarkDown export mindnode") {
//         wp_delete_post($eachpost->ID, true);
//         // }
//     }
//     $args = array(
//         "hide_empty" => 0,
//         "taxonomy"       => "archive-category"
//     );
//     $types = get_terms($args);

//     foreach ($types as $type) {
//         wp_delete_term($type->term_id, 'archive-category');
//     }
// }
require_once(plugin_dir_path(__FILE__) . '/action-scheduler/action-scheduler.php');

function tmy_schedule_download_parse_task()
{
    if (false === as_has_scheduled_action('tmy_shedule_background_task')) {
        as_schedule_recurring_action(time() + 5, MINUTE_IN_SECONDS, 'tmy_shedule_background_task');
    }
}
add_action('init', 'tmy_schedule_download_parse_task');

function tmy_schedule_download_parse_pdfs()
{
    file_put_contents('php://stdout', 'Background task ran ' . time(), FILE_APPEND);
    download_files_from_content_links();
}
add_action('tmy_shedule_background_task', 'tmy_schedule_download_parse_pdfs');
add_filter('wp_check_filetype_and_ext', function ($data, $file, $filename, $mimes) {
    $filetype = wp_check_filetype($filename, $mimes);
    return [
        'ext'             => $filetype['ext'],
        'type'            => $filetype['type'],
        'proper_filename' => $data['proper_filename']
    ];
}, 10, 4);

function cc_mime_types($mimes)
{
    $mimes['md'] = 'image/md+xml';
    return $mimes;
}
add_filter('upload_mimes', 'cc_mime_types');

function fix_svg()
{
    echo '<style type="text/css">
        .attachment-266x266, .thumbnail img {
             width: 100% !important;
             height: auto !important;
        }
        .mb-3 {
            margin-bottom: .3em !important;
        }
        </style>';
}
add_action('admin_head', 'fix_svg');

add_action('admin_menu', 'tmy_markdown_plugin_setup_menu');
if (is_admin()) {
    add_action('admin_init', 'tmy_markdown_plugin_settings');
}
function tmy_markdown_plugin_settings()
{
    register_setting('tmy-option-group', 'tmy_open_ai_api_key');
    register_setting('tmy-option-group', 'tmy_open_ai_batch_size');
}
function tmy_markdown_plugin_setup_menu()
{
    add_menu_page('Markdown upload page', 'Markdown upload', 'manage_options', 'tmy_markdown-plugin', 'tmy_markdown_init');
}
function tmy_markdown_init()
{
    tmy_markdown_handle_post();
    global $max_file_download;
    $attachments = get_posts(array('post_type' => 'attachment', 'numberposts' => -1));
    $parsable_docs = array_filter($attachments, function ($attachment) {
        return $attachment->post_mime_type === "application/pdf" || $attachment->post_mime_type === "application/vnd.openxmlformats-officedocument.wordprocessingml.document";
    });
    $placeholders = array_filter($attachments, function ($attachment) {
        return $attachment->post_content == 'placeholder' && ($attachment->post_mime_type === "application/pdf" || $attachment->post_mime_type === "application/vnd.openxmlformats-officedocument.wordprocessingml.document");
    });
?>
    <h1>Import markdown file to archive post_type hierarchy</h1>

    <!-- Form to handle the upload - The enctype value here is very important -->
    <form method="post" action="options.php">
        <h2>Set OpenAI API key</h2>
        <?php settings_fields('tmy-option-group'); ?>
        <input type='text' class="regular-text" id="first_field_id" name="tmy_open_ai_api_key" value="<?php echo get_option('tmy_open_ai_api_key'); ?>">
        <h2>Set max batch size</h2>
        <?php settings_fields('tmy-option-group'); ?>
        <input type='text' class="regular-text" id="first_field_id" name="tmy_open_ai_batch_size" value="<?php echo get_option('tmy_open_ai_batch_size'); ?>">
        <?php submit_button(); ?>
    </form>
    <form method="post" enctype="multipart/form-data">
        <h2>Upload markdown file</h2>
        <input type='file' id='tmy_markdown_upload_pdf' name='tmy_markdown_upload_pdf'></input>
        <?php submit_button('Upload') ?>
    </form>
    <form method="post" enctype="multipart/form-data">
        <h2>Download Files from dropbox-links and attach to archives</h2>
        <?php
        $allposts = get_posts(array('post_type' => 'archive', 'numberposts' => -1));
        $all_content_links = array();
        foreach ($allposts as $eachpost) {
            $all_content_links = array_merge($all_content_links, wp_extract_urls($eachpost->post_content));
        }
        ?>
        <?php echo 'Total links found in all archive posts: '. count($all_content_links); ?>
        <input type="hidden" name="download_files_from_content_links" value="true" />
        <?php submit_button('Download PDFs') ?>
    </form>
    <form method="post" enctype="multipart/form-data">
        <h2>Extract text from PDFs and add to media description</h2>
        Total Media found: <?php echo count($attachments); ?>
        <br/>
        Media type PDF or docx: <?php echo count($parsable_docs); ?>
        <br/>
        PDFs or docx without description: <?php echo count($placeholders); ?>
        <br />
        <?php
        foreach ($placeholders as $eachpost) {
            echo '<a href="' . get_edit_post_link($eachpost->ID) . '">' . $eachpost->post_title . '</a><br/>';
        }
        ?>
        <?php if (empty(get_option('tmy_open_ai_api_key'))) : ?>
            First set API key to interact with OpenAI
        <?php else : ?>
            <input type="hidden" name="get_pdf_textcontent" value="true" />
            <?php submit_button('Extract text') ?>
        <?php endif; ?>
    </form>
    Out of performance reasons, just batch <?php echo $max_file_download ?> documents at a time.
<?php
}
function attachment_url_to_path($url)
{
    $parsed_url = parse_url($url);
    if (empty($parsed_url['path'])) return false;
    $file = ABSPATH . ltrim($parsed_url['path'], '/');
    if (file_exists($file)) return $file;
    return false;
}
function tmy_markdown_handle_post()
{
    global $max_file_download;
    // First check if the file appears on the _FILES array
    if (isset($_FILES['tmy_markdown_upload_pdf'])) {
        // Use the wordpress function to upload
        // tmy_markdown_upload_pdf corresponds to the position in the $_FILES array
        // 0 means the content is not associated with any other posts
        $uploaded = media_handle_upload('tmy_markdown_upload_pdf', 0);
        // Error checking using WP functions
        if (is_wp_error($uploaded)) {
            echo "\n\rError uploading file: " . $uploaded->get_error_message();
        } else {
            test_convert($uploaded);
            echo "\n\rFile upload, import of category structure and post-creation successful!";
        }
    } else if ($_POST && isset($_POST['download_files_from_content_links'])) {
        download_files_from_content_links();
    } else if ($_POST && isset($_POST['get_pdf_textcontent'])) {
        $id = $_POST['get_pdf_textcontent'];
        if ($id !== 'true') {
            // individual trigger from media
            get_pdf_textcontent($id);
        } else {
            // bulk trigger from plugin options
            $attachments = get_posts(array('post_type' => 'attachment', 'numberposts' => -1));
            $placeholders = array_filter($attachments, function ($attachment) {
                return $attachment->post_content == 'placeholder' && ($attachment->post_mime_type === "application/pdf" || $attachment->post_mime_type === "application/vnd.openxmlformats-officedocument.wordprocessingml.document");
            });
            $i = 0;
            echo "<h2>Extracted text from archive-posts: </h2>";
            foreach ($placeholders as $eachpost) {
                if ($i < $max_file_download) {
                    try {
                        get_pdf_textcontent($eachpost->ID, false);
                        echo '<a target="_blank" href="' . get_edit_post_link($eachpost->ID) . '">' . $eachpost->post_title . '</a><br/>';
                        $i++;
                    } catch (Throwable $t) {
                        echo $t;
                        echo '<br/>';
                        $i++;
                        continue;
                    }
                }
            }
        }
    } else if ($_POST && isset($_POST['tmy_send_to_ai'])) {
        $id = $_POST['tmy_send_to_ai'];
        tmy_send_to_ai($id);
    }
};
add_action('add_meta_boxes', function () {
    add_meta_box('att_thumb_display', 'Attachmed images', function ($post) {
        $args = array(
            'post_type' => 'attachment',
            'post_parent' => $post->ID
        );
        echo '<ul>';
        foreach (get_posts($args) as $image) {
            echo "<a target='_blank' href='" . get_edit_post_link($image->ID) . "'>" . $image->post_title . "</a>";
        }
        echo '</ul>';
    }, 'archive');
});
function get_pdf_textcontent($id, $return = true)
{
    $post = get_post($id);
    $filepath = get_attached_file($post->ID);
    $text_content = "";
    // Parse pdf file and build necessary objects.
    $file_parts = pathinfo($filepath);
    if ($file_parts['extension'] === 'pdf') {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf    = $parser->parseFile($filepath);
        $text_content = trim(preg_replace('/\s\s+/', ' ', $pdf->getText()));
        // Retrieve all details from the pdf file.
        // $details  = $pdf->getDetails();
        // Loop over each property to extract values (string or array).
        // $meta_accumulation = $filepath . "\n";
        // foreach ($details as $property => $value) {
        //     if (is_array($value)) {
        //         $value = implode(', ', $value);
        //     }
        //     $meta_accumulation += $property . ' => ' . $value . "\n";
        // }
        // $details  = $pdf->getDetails();
        // $meta = "Meta: \n\r";
        // foreach ($details as $property => $value) {
        //     if (is_array($value)) {
        //         $value = implode(', ', $value);
        //     }
        //     $meta .= $property . ' => ' . $value . "\n";
        // }
        // $result = $meta . "\n\rTextcontent: \n\r" . str_replace("\t", '', $text_content);

    }
    if ($file_parts['extension'] === 'docx') {
        $content = '';
        $zip = zip_open($filepath);
        if (!$zip || is_numeric($zip)) return false;
        while ($zip_entry = zip_read($zip)) {
            if (zip_entry_open($zip, $zip_entry) == FALSE) continue;
            if (zip_entry_name($zip_entry) != "word/document.xml") continue;
            $content .= zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
            zip_entry_close($zip_entry);
        } // end while
        zip_close($zip);
        $content = str_replace('</w:r></w:p></w:tc><w:tc>', " ", $content);
        $content = str_replace('</w:r></w:p>', "\r\n", $content);
        $text_content = strip_tags($content);
    }
    $postid = $post->ID;
    $result = str_replace("\t", '', trim($text_content));
    $updated_post = array(
        'ID' => $postid,
        'post_content' => $result,
        // 'post_excerpt' => "replaced by AI summary"
    );
    $err = wp_update_post($updated_post, true, true);
    file_put_contents('php://stdout', 'PDF content saved ' . time(), FILE_APPEND);
    if (is_wp_error($err)) {
        var_dump($err);
        error_log(print_r($err, 1));
    } else {
        if ($return) {
            echo $result;
            die;
        }
    }
}
function download_files_from_content_links()
{
    global $max_file_download;
    if (!function_exists('post_exists')) {
        require_once(ABSPATH . 'wp-admin/includes/post.php');
    }
    if (!function_exists('wp_get_current_user')) {
        include(ABSPATH . "wp-includes/pluggable.php");
    }
    $allposts = get_posts(array('post_type' => 'archive', 'numberposts' => -1));
    $i = 0;
    foreach ($allposts as $eachpost) {
        $post_content_links = wp_extract_urls($eachpost->post_content);
        if (count($post_content_links) > 0) {
            foreach ($post_content_links as $post_content_link) {
                $basename = basename($post_content_link);
                $basefilename = urldecode(preg_replace('/\?.*/', '', $basename));
                $filename = preg_replace('/#[^?|^&]*/', '', $basefilename);
                $file_parts = pathinfo($filename);
                if (
                    !post_exists($filename)
                    && $filename !== 'edit'
                    && $filename !== 'view'
                    // && isset($file_parts["extension"]) && $file_parts['extension'] === 'docx'
                ) {
                    if ($i < $max_file_download) {
                        $query = parse_url($post_content_link, PHP_URL_QUERY);
                        if ($query) {
                            $post_content_link .= '&raw=1&dl=1';
                        } else {
                            $post_content_link .= '?raw=1&dl=1';
                        }
                        $post_content_link = str_replace('dl=0', '', $post_content_link);
                        $contents = file_get_contents($post_content_link, false, stream_context_create(['http' => ['ignore_errors' => true]]));
                        // var_dump($http_response_header);
                        $post_id = $eachpost->ID;
                        $filetype = wp_check_filetype($filename, null);
                        $wp_upload_dir = wp_upload_dir();

                        $new_file_path = $wp_upload_dir['path'] . '/' . $filename;
                        if (!file_exists($filename)) {
                            file_put_contents($new_file_path, $contents);
                        }
                        $attachment = array(
                            'guid'           => $new_file_path,
                            'post_mime_type' => $filetype['type'],
                            'post_title'     => $filename,
                            'post_status'    => 'inherit',
                            'post_content'   => "placeholder",
                            'post_excerpt'   => $post_content_link,
                        );
                        $attachment_id = wp_insert_attachment($attachment, $new_file_path, $post_id);
                        require_once(ABSPATH . 'wp-admin/includes/image.php');
                        $attach_data = wp_generate_attachment_metadata($attachment_id, $new_file_path);
                        wp_update_attachment_metadata($attachment_id, $attach_data);
                        $i++;
                    }
                } else {
                    // echo "\n\rPOST is already existing";
                }
            }
        }
    }
}

function tmy_send_to_ai($id)
{
    $post = get_post($id);
    $trimmed = trim(preg_replace('/\s\s+/', ' ', $post->post_content));
    $trimmed = preg_replace('/\d{1,2}\s{1}/', "", $trimmed);
    $max_tokens = 64;
    preg_match('/[A-Z]{1}[a-z]*\s[a-z]*\s[a-z]*\s[a-z]*\s[a-z]*\s[^\.]*\./', $trimmed, $indexOfFirstSentence, PREG_OFFSET_CAPTURE);
    $shortened = substr($trimmed, $indexOfFirstSentence && $indexOfFirstSentence[0] ? $indexOfFirstSentence[0][1] : 0, 2049 - $max_tokens);
    $tldr = $shortened . "\n\ntl;dr:\n";
    $args = array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . get_option('tmy_open_ai_api_key'),
        ),
        "body" => json_encode(array(
            "prompt" => $tldr,
            "temperature" => 0.7,
            "max_tokens" => $max_tokens,
            "top_p" => 1,
            "frequency_penalty" => 0.2,
            "presence_penalty" => 0,
            "stop" => ["\n\ntl;dr:\n"]
        ))
    );
    $response = wp_remote_post('https://api.openai.com/v1/engines/davinci/completions', $args);
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        echo "Something went wrong: $error_message";
        die;
    }
    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body);
    $post_data = "";

    foreach ($json->choices as $choice) {
        $post_data .= $choice->text . "\n\r";
    }
    $data = array(
        'ID' => $post->ID,
        'post_excerpt' => $post_data
    );
    // var_dump('$data' . $data);
    $res = wp_update_post($data, true);
    echo $post_data;
    die;
}
add_action('add_meta_boxes', 'tmy_add_your_meta_box2');
function tmy_add_your_meta_box2()
{
    global $post;
    if ($post->post_mime_type === "application/pdf" || $post->post_mime_type === "application/vnd.openxmlformats-officedocument.wordprocessingml.document") {
        add_meta_box('tmy_markdown_upload_pdf', 'Save PDF content to Mediacaption', 'tmy_function_of_metabox', 'attachment', 'side', 'high');
    }
}
function tmy_function_of_metabox()
{
?>
    <?php if (empty(get_option('tmy_open_ai_api_key'))) : ?>
        <?php echo "Please set your OpenAI API key in the <a href='/wp-admin/admin.php?page=tmy_markdown-plugin'>settings page</a>"; ?>
    <?php else : ?>
        <form method="post" enctype="multipart/form-data">
            <button type="submit" class="button button-primary button-large mb-3" value="" id="get_pdf_textcontent">Save PDF content to Caption<span class="spinner hidden-field"></span></button>
            <button type="submit" class="button button-primary button-large mb-3" value="" id="tmy_get_ai">Summarize w. Open Ai to Description<span class="spinner hidden-field"></span></button>
        </form>
    <?php endif; ?>
    <?php }

add_action('admin_head', 'my_action_javascript');
function my_action_javascript()
{
    global $pagenow;
    global $post;
    if ($pagenow === "post.php" && $post->post_type === "attachment") {
    ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#get_pdf_textcontent, #tmy_get_ai').click(function(e) {
                    e.preventDefault()
                    e.target.disabled = true
                    e.target.querySelector(".spinner").classList.add("is-active");
                    e.target.querySelector(".spinner").classList.remove("hidden-field");
                    var data = {
                        action: 'tmy_ajax_handler',
                    };
                    if (e.target.id === "get_pdf_textcontent") {
                        data.get_pdf_textcontent = <?php echo $post->ID ?>

                    } else {
                        data.tmy_send_to_ai = <?php echo $post->ID ?>
                    }
                    // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
                    $.post(ajaxurl, data, function(response) {
                        if (response.includes("There has been a critical error on this website")) {
                            var error = document.createElement("div");
                            var errorParagraph = document.createElement("p");
                            errorParagraph.innerHTML = response;
                            error.classList.add("error");
                            error.classList.add("notice");
                            error.appendChild(errorParagraph);
                            document.querySelector(".wrap").prepend(error);
                        } else {
                            value = response;
                            e.target.disabled = false
                            e.target.querySelector(".spinner").classList.remove("is-active");
                            e.target.querySelector(".spinner").classList.add("hidden-field");
                            if (e.target.id === "get_pdf_textcontent") {
                                document.getElementById("attachment_content").value = response;
                            } else {
                                document.getElementById("attachment_caption").value = response;
                            }
                        }
                    });
                });
            });
        </script>
    <?php
    }
}
add_action('wp_ajax_tmy_ajax_handler', 'tmy_markdown_handle_post');
/*
* Creating a function to create our CPT
*/

function custom_post_type()
{

    // Set UI labels for Custom Post Type
    $labels = array(
        'name'                => _x('Archives', 'Post Type General Name', 'twentytwenty'),
        'singular_name'       => _x('Archive', 'Post Type Singular Name', 'twentytwenty'),
        'menu_name'           => __('Archives', 'twentytwenty'),
        'parent_item_colon'   => __('Parent Archive', 'twentytwenty'),
        'all_items'           => __('All Archives', 'twentytwenty'),
        'view_item'           => __('View Archive', 'twentytwenty'),
        'add_new_item'        => __('Add New Archive', 'twentytwenty'),
        'add_new'             => __('Add New', 'twentytwenty'),
        'edit_item'           => __('Edit Archive', 'twentytwenty'),
        'update_item'         => __('Update Archive', 'twentytwenty'),
        'search_items'        => __('Search Archive', 'twentytwenty'),
        'not_found'           => __('Not Found', 'twentytwenty'),
        'not_found_in_trash'  => __('Not found in Trash', 'twentytwenty'),
    );

    // Set other options for Custom Post Type

    $args = array(
        'label'               => __('archive', 'twentytwenty'),
        'description'         => __('Archive news and reviews', 'twentytwenty'),
        'labels'              => $labels,
        'rewrite' => array(
            'slug'       => 'archive',
            'with_front' => false,
        ),
        // Features this CPT supports in Post Editor
        'supports'            => array('title', 'editor', 'excerpt', 'author', 'thumbnail', 'comments', 'revisions', 'custom-fields', 'page-attributes'),
        // You can associate this CPT with a taxonomy or custom taxonomy. 
        'taxonomies'          => array('archive-category'),
        /* A hierarchical CPT is like Pages and can have
        * Parent and child items. A non-hierarchical CPT
        * is like Posts.
        */
        'hierarchical'        => true,
        'public'              => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_nav_menus'   => true,
        'show_in_admin_bar'   => true,
        'menu_position'       => 5,
        'can_export'          => true,
        'has_archive'         => true,
        'exclude_from_search' => false,
        'publicly_queryable'  => true,
        'capability_type'     => 'post',
        'show_in_rest' => true,

    );

    // Registering your Custom Post Type
    register_post_type('archive', $args);
}
function tr_create_my_taxonomy()
{

    register_taxonomy(
        'archive-category',
        'archive',
        array(
            'label' => __('Archive category'),
            'rewrite' => array('slug' => 'Archive category'),
            'hierarchical' => true,
        )
    );
}
add_action('init', 'tr_create_my_taxonomy');
/* Hook into the 'init' action so that the function
* Containing our post type registration is not 
* unnecessarily executed. 
*/

add_action('init', 'custom_post_type', 0);

function test_convert($id)
{
    $allposts = get_posts(array('post_type' => 'archive', 'numberposts' => -1));
    foreach ($allposts as $eachpost) {
        wp_delete_post($eachpost->ID, true);
    }
    $args = array(
        "hide_empty" => 0,
        "taxonomy"       => "archive-category"
    );
    $types = get_terms($args);

    foreach ($types as $type) {
        wp_delete_term($type->term_id, 'archive-category');
    }
    $file = get_attached_file($id);
    $file_array = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $id = 0;
    $id_sub = 0;
    $id_sub_sub = 0;
    $post_id = 0;
    $post_parent_content = "";
    for ($i = 0; $i < count($file_array); $i++) {
        preg_match('/^(\s+)/', $file_array[$i], $matches);
        $identSize = strlen(count($matches) ? $matches[1] : "");
        if ((strpos($file_array[$i], "#") === 0 || $identSize === 0) && $post_parent_content !== "") {
            $data = array(
                'ID' => $post_id,
                'post_content' => $post_parent_content,
            );

            wp_update_post($data);
            $post_parent_content = "";
        }
        $types = get_terms($args);
        preg_match_all("/\[.*\]/", substr($file_array[$i], 2), $matches);
        preg_match_all("/\(.*\)/", substr($file_array[$i], 2), $matches2);
        if (strpos($file_array[$i], "#") === 0 && $file_array[$i][1] !== "#") {
            $id = wp_insert_term($matches[0] ? preg_replace('/\*\*(.*)\*\*/', "$1", substr($matches[0][0], 1, -1)) : substr($file_array[$i], 1), "archive-category", array('description' => $matches2[0] ? substr($matches2[0][0], 1, -1) : substr($file_array[$i], 1), 'parent' => 0));
        } else if ($file_array[$i][0] === "#" && $file_array[$i][1] === "#" && $file_array[$i][2] !== "#") {
            $id_sub = wp_insert_term($matches[0] ? preg_replace('/\*\*(.*)\*\*/', "$1", substr($matches[0][0], 1, -1)) : substr($file_array[$i], 2), "archive-category", array('description' => $matches2[0] ? substr($matches2[0][0], 1, -1) : substr($file_array[$i], 2), 'parent' => $id['term_id']));
        } else if ($file_array[$i][0] === "#" && $file_array[$i][1] === "#" && $file_array[$i][2] === "#") {
            $id_sub_sub = wp_insert_term($matches[0] ? preg_replace('/\*\*(.*)\*\*/', "$1", substr($matches[0][0], 1, -1)) : substr($file_array[$i], 3), "archive-category", array('description' => $matches2[0] ? substr($matches2[0][0], 1, -1) : substr($file_array[$i], 3), 'parent' => $id_sub['term_id']));
        } else {
            if ($identSize === 0) {
                $my_post = array(
                    'post_title'    => $matches[0] ? preg_replace('/\*\*(.*)\*\*/', "$1", substr($matches[0][0], 1, -1)) : substr($file_array[$i], 2),
                    'post_content'  => $matches2[0] ? substr($matches2[0][0], 1, -1) : substr($file_array[$i], 2),
                    'post_status'   => 'publish',
                    'post_author'   => 1,
                    'post_type'   => "archive"
                );
                $post_id = wp_insert_post($my_post);
                wp_set_object_terms($post_id, array($id["term_id"], $id_sub["term_id"], $id_sub_sub["term_id"]), 'archive-category', true);
            } else {
                preg_match_all("/\[.*\]/", substr($file_array[$i], 2), $matches);
                preg_match_all("/\(.*\)/", substr($file_array[$i], 2), $matches2);
                $line_content = count($matches[0]) > 0 ? "<a href='" . substr($matches2[0][0], 1, -1) . "'>" . preg_replace('/\*\*(.*)\*\*/', "<b>$1</b>",  substr($matches[0][0], 1, -1))  . "</a>" : substr($file_array[$i], 2);
                $post_parent_content = $post_parent_content . "<p style='padding-left: " . ($identSize - 1) * 40 . "px;'>" . $line_content . '</p>';
            }
        }
    }
}



add_filter('manage_archive_posts_columns', function ($columns) {
    $offset = array_search('author', array_keys($columns));
    return array_merge(array_slice($columns, 0, $offset), ['ancestor' => __('Ancestor', 'textdomain'), 'archive-category' => __('Archive category', 'textdomain')], array_slice($columns, $offset, null));
});

add_action('manage_pages_custom_column', function ($column_key, $post_id) {
    if ($column_key == 'ancestor') {
        $ancestors = array_reverse(get_ancestors($post_id, 'archive', 'post_type'));
        foreach ($ancestors as $post_ancestor) {
            echo '<a href="' . get_edit_post_link($post_ancestor) . '">' . get_the_title($post_ancestor) . '</a>';
            echo '<br/>';
        }
    }
    if ($column_key == 'archive-category') {
        $ancestors = array_reverse(get_the_terms($post_id, 'archive-category'));
        foreach ($ancestors as $post_ancestor) {
            echo '<a href="' . get_term_link($post_ancestor->term_id, 'archive-category') . '">' . $post_ancestor->name . '</a>';
            echo '<br/>';
        }
    }
}, 10, 2);

//[import_markdown_structure_search]
function import_markdown_structure_search_func($atts)
{
    $html = '<form><input type="text" id="search-input" placeholder="Search..."></form><div id="search-results"></div>';
    return $html;
}
add_shortcode('import_markdown_structure_search', 'import_markdown_structure_search_func');


add_action('wp_footer', 'my_ajax_without_file');

function my_ajax_without_file()
{ ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#search-input').on('input', function(e) {
                // $('#search-submit').on('click', function(e) {
                // e.preventDefault()
                ajaxurl = '<?php echo admin_url('admin-ajax.php') ?>'; // get ajaxurl
                var data = {
                    'action': 'frontend_action_without_file', // your action name 
                    // 'query': document.getElementById("search-input").value // some additional data to send
                    'query': e.target.value // some additional data to send
                };
                jQuery.ajax({
                    url: ajaxurl, // this will point to admin-ajax.php
                    type: 'POST',
                    data: data,
                    success: function(resp) {
                        let response = JSON.parse(resp);
                        var resultsDiv = document.createElement('div')
                        response.map(post => {
                            var link = document.createElement('a');
                            var contentDiv = document.createElement('div');
                            link.innerHTML = post.post_title;
                            link.href = post.post_link;
                            contentDiv.innerHTML = post.post_content.replace(e.target.value, `<code style="background-color: yellow;">${e.target.value}</code>`);
                            resultsDiv.appendChild(link)
                            resultsDiv.appendChild(contentDiv)
                        })
                        document.getElementById('search-results').innerHTML = resultsDiv.innerHTML
                    }
                });
            });
        })
    </script>
<?php
}

add_action("wp_ajax_frontend_action_without_file", "frontend_action_without_file");
add_action("wp_ajax_nopriv_frontend_action_without_file", "frontend_action_without_file");

function frontend_action_without_file()
{
    global $wpdb;
    $query = $_POST['query'];
    $result = $wpdb->get_results("SELECT * FROM wp_posts WHERE post_content LIKE '%$query%'");
    $posts = array();
    if ($result) {
        foreach ($result as $pageThing) {
            array_push($posts, array(
                "post_link" => get_permalink($pageThing->ID),
                "post_title" => $pageThing->post_title,
                "post_content" => substr($pageThing->post_content,  strpos($pageThing->post_content, $query) - 100, 200),
            ));
        }
    }
    echo json_encode($posts);
    wp_die();
}
