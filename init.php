<?php

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

add_action('add_meta_boxes', 'tmy_add_your_meta_box2');
function tmy_add_your_meta_box2()
{
    global $post;
    if ($post->post_mime_type === "application/pdf" || $post->post_mime_type === "application/vnd.openxmlformats-officedocument.wordprocessingml.document") {
        add_meta_box('tmy_markdown_upload_pdf', 'Save PDF content to Mediacaption', 'tmy_function_of_metabox', 'attachment', 'normal', 'high');
        add_meta_box('tmy_markdown_upload_pdf_3', 'Preview of PDF & Thumbnail', 'tmy_function_of_metabox_3', 'attachment', 'normal', 'high');
    }
}
function tmy_save_meta_box($post_id)
{
    if (array_key_exists('tmy_field', $_POST)) {
        update_post_meta($post_id, '_tmy_meta_key', $_POST['tmy_field']);
    }
    if (array_key_exists('tmy_ai_field', $_POST)) {
        update_post_meta($post_id, '_tmy_meta_custom_field_send_to_ai', $_POST['tmy_ai_field']);
    }
    if (array_key_exists('tmy_ai_field', $_POST)) {
        $data = array(
            'ID' => $post_id,
            'post_content' => $_POST['tmy_descritption_field']
        );
        // prevent infinite loop
        remove_all_actions('edit_attachment');
        wp_update_post($data, true);
        if (is_wp_error($post_id)) {
            $errors = $post_id->get_error_messages();
            foreach ($errors as $error) {
                echo $error;
            }
        }
    }
}
add_action('edit_attachment', 'tmy_save_meta_box', 1, 2);
function tmy_function_of_metabox_3($post)
{
?>
    <div class="d-flex">
        <embed src="<?php echo wp_get_attachment_url($post->ID); ?>" type="application/pdf" width="100%" height="auto"></embed>
        <?php echo wp_get_attachment_image($post->ID, 'normal', false, array('class' => 'halfimg')) ?>
    </div>
<?php
}
function tmy_function_of_metabox($post)
{
    $value = get_post_meta($post->ID, '_tmy_meta_key', true);
?>
    <?php if (empty(get_option('tmy_open_ai_api_key'))) : ?>
        <?php echo "Please set your OpenAI API key in the <a href='/wp-admin/admin.php?page=tmy_markdown-plugin'>settings page</a>"; ?>
    <?php else : ?>
        <label for="tmy_field">Original markdown link</label>
        <form method="post" enctype="multipart/form-data">
            <input value="<?php echo $value ?>" type='text' class="widefat urlfield" name="tmy_field" id="tmy_field" disabled="disabled">

            <h2>
                Description
            </h2>
            <textarea rows="20" type='text' class="widefat urlfield" name="tmy_descritption_field" id="tmy_descritption_field"><?php echo $post->post_content; ?></textarea>
            <br />
            <br />
            <button type="submit" class="button button-primary button-large mb-3" value="" id="get_pdf_textcontent">Overwrite with text-scan<span class="spinner hidden-field"></span></button>


            <?php
            $value = get_post_meta($post->ID, '_tmy_meta_custom_field_send_to_ai', true);
            global $max_tokens;
            ?>
            <h2>
                Text sent to AI
            </h2>
            <textarea rows="10" type='text' class="widefat urlfield" name="tmy_ai_field" id="tmy_ai_field"><?php echo $value ?></textarea>
            <br />
            <br />
            <button type="submit" class="button button-primary button-large mb-3" value="" id="tmy_get_ai">Get summary to `Caption`<span class="spinner hidden-field"></span></button>
            <br />
            <br />

            <?php
            echo strlen($value) . ' letters which are ~ ' . strlen($value) / 4 . ' tokens. Minus ' . $max_tokens . ' defined max_tokens = ' . intval((strlen($value) / 4 - $max_tokens)) . ' tokens that get send to OpenAI. ';
            if ((strlen($value) / 4 - $max_tokens) > 2048) {
                echo "<span style='color: red;'>" . intval(strlen($value) / 4 - $max_tokens - 2048) . " tokens too much! Please shorten your text.</span>";
            }
            ?>
            <br />
            <h2>
                AI Result
            </h2>
            <textarea rows="10" type='text' class="widefat urlfield" name="tmy_result_field" id="tmy_result_field"><?php echo $post->post_excerpt;  ?></textarea>
        </form>
    <?php endif; ?>
    <?php
}
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
                                window.scrollTo({
                                    top: 0,
                                    behavior: "smooth"
                                });
                            } else {
                                value = response;
                                if (e.target.id === "get_pdf_textcontent") {
                                    document.getElementById("tmy_descritption_field").value = response;
                                    document.getElementById("tmy_ai_field").value = response;
                                } else {
                                    document.getElementById("tmy_result_field").value = response;
                                }
                            }
                        })
                        .done(function() {
                            console.log("second success");
                            e.target.disabled = false
                            e.target.querySelector(".spinner").classList.remove("is-active");
                            e.target.querySelector(".spinner").classList.add("hidden-field");
                        })
                        .fail(function() {
                            console.log("error");
                        })
                        .always(function() {
                            console.log("finished");
                        });

                    ;
                });
            });
        </script>
    <?php
    }
}

add_action('wp_ajax_tmy_ajax_handler', 'tmy_markdown_handle_post');
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
                return $attachment->post_content == '' && ($attachment->post_mime_type === "application/pdf" || $attachment->post_mime_type === "application/vnd.openxmlformats-officedocument.wordprocessingml.document");
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
        if ($id !== 'true') {
            // individual trigger from media
            tmy_send_to_ai($id);
        } else {
            // bulk trigger from plugin options
            $attachments = get_posts(array('post_type' => 'attachment', 'numberposts' => -1));
            $parsable_docs = array_filter($attachments, function ($attachment) {
                return $attachment->post_mime_type === "application/pdf" || $attachment->post_mime_type === "application/vnd.openxmlformats-officedocument.wordprocessingml.document";
            });
            $media_with_just_link_excerpt = array_filter($parsable_docs, function ($attachment) {
                return $attachment->post_excerpt === "" && $attachment->post_content !== "";
            });
            $i = 0;
            echo "<h2>Summarized: </h2>";
            foreach ($media_with_just_link_excerpt as $key => $eachpost) {
                if ($i < $max_file_download) {
                    try {
                        echo '<a target="_blank" href="' . get_edit_post_link($eachpost->ID) . '">' . $eachpost->post_title . '</a><br/>';
                        tmy_send_to_ai($eachpost->ID, false);
                        echo '<br/>';
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

// [import_markdown_structure_search]
function import_markdown_structure_search_func($atts)
{
    $html = '<form class="position-relative tmy-form"><input type="text" id="tmy-search-input" placeholder="Search..."><div id="tmy-search-results-spinner" class="d-none"><span>+</span></div></form><div class="position-relative"><div id="tmy-search-results"></div></div>';
    return $html;
}
add_shortcode('import_markdown_structure_search', 'import_markdown_structure_search_func');

function toshodex_search_scripts()
{
    wp_enqueue_script('toshodex_search_scripts', plugin_dir_url(__FILE__) . '/index.js', '', '1.1.0', true);
    wp_localize_script('toshodex_search_scripts', 'toshodex_search_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wp-pageviews-nonce'),
        'is_user_logged_in' => is_user_logged_in(),
        'is_single' => is_single()
    ));
}

add_action('wp_enqueue_scripts', 'toshodex_search_scripts');
add_action("wp_ajax_frontend_searchaction", "frontend_searchaction");
add_action("wp_ajax_nopriv_frontend_searchaction", "frontend_searchaction");
function utf8ize($d)
{
    if (is_array($d)) {
        foreach ($d as $k => $v) {
            $d[$k] = utf8ize($v);
        }
    } else if (is_string($d)) {
        return utf8_encode($d);
    }
    return $d;
}
function frontend_searchaction()
{
    global $wpdb;
    $query = strtolower($_POST['query']);
    $result = $wpdb->get_results("SELECT * FROM wp_posts WHERE (post_type = LOWER('archive') OR post_type = LOWER('attachment')) AND (post_content LIKE '%$query%' OR post_excerpt LIKE '%$query%') ");
    $posts = array();
    $matches = 0;
    if ($result && $_POST['query'] !== "") {
        foreach ($result as $queriedPost) {
            $html = $queriedPost->post_content;
            $lastPos = 0;
            $positions = array();
            while (($lastPos = strpos($html, $query, $lastPos)) !== false) {
                $positions[] = $lastPos;
                $lastPos = $lastPos + strlen($query);
            }
            $search_matches = "";
            foreach ($positions as $key => $value) {
                $matches++;
                $search_matches = $search_matches . "<div class='" . ($key > 5 ? "d-none'" : "") . "'><i>" . $key . "Line " . $value . "</i>: ... " . substr($queriedPost->post_content, $value > 80 ? $value - 80 : 0, strlen($query) + 100) . " ... </div>";
            }
            if (count($positions) > 10) {
                $remaining = count($positions) - 10;
                $search_matches = $search_matches . "<button class='btn btn-primary tmy_show_all_button' style='margin-top: 20px;'>Show " . $remaining . " more</button>";
            }
            array_push($posts, array(
                "post_link" => get_permalink($queriedPost->ID),
                "post_title" => $queriedPost->post_title,
                "post_type" => $queriedPost->post_type,
                "post_content" => $search_matches,
                "post_excerpt" => strlen($queriedPost->post_excerpt) > 400 ? substr($queriedPost->post_excerpt, 0, 400) . "..." : $queriedPost->post_excerpt,
            ));
        }
    }
    echo json_encode(array("matches" => $matches, "posts" => utf8ize($posts)));
    wp_die();
}

add_filter('the_content', 'filter_the_content_in_the_main_loop', 1);

function filter_the_content_in_the_main_loop($content)
{

    global $post;
    // Check if we're inside the main loop in a single Post.
    if (get_post_type() === "archive" || get_post_type() === "attachment" && is_singular() && in_the_loop() && is_main_query()) {
        return "<h2>Summary:</h2><code>" . $post->post_excerpt . "</code><br/> <hr/><br/><h2>Content:</h2>:" . $content;
    }

    return $content;
}

function tmy_add_categories_to_attachments()
{
    register_taxonomy_for_object_type('category', 'attachment');
}
add_action('init', 'tmy_add_categories_to_attachments');
// apply tags to attachments
function tmy_add_tags_to_attachments()
{
    register_taxonomy_for_object_type('post_tag', 'attachment');
}
add_action('init', 'tmy_add_tags_to_attachments');

function remove_item_from_menu()
{
    $user = wp_get_current_user();
    if (
        in_array('editor', (array) $user->roles)
    ) {

        remove_menu_page('index.php');                  //Dashboard
        remove_menu_page('jetpack');                    //Jetpack* 
        remove_menu_page('edit.php');                   //Posts
        // remove_menu_page('upload.php');                 //Media
        remove_menu_page('edit.php?post_type=page');    //Pages
        remove_menu_page('edit-comments.php');          //Comments
        remove_menu_page('themes.php');                 //Appearance
        remove_menu_page('profile.php');                 //Profile
        remove_menu_page('plugins.php');                //Plugins
        remove_menu_page('users.php');                  //Users
        remove_menu_page('tools.php');                  //Tools
        remove_menu_page('options-general.php');        //Settings
        remove_submenu_page('themes.php', 'theme-editor.php'); // remove submenu called theme edititor inside appearance
        remove_submenu_page('themes.php', 'widgets.php'); // removes widgets submenu

    }
}
add_action('admin_menu', 'remove_item_from_menu');
add_filter('ajax_query_attachments_args', 'show_current_user_attachments', 10, 1);

function show_current_user_attachments($query = array())
{
    $user_id = get_current_user_id();
    if ($user_id) {
        $query['author'] = $user_id;
    }
    return $query;
}
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('styles', plugin_dir_url(__FILE__) . 'style.css', array(), '1.13', 'all');
});
function wpdocs_enqueue_custom_admin_style()
{
    wp_register_style('custom_wp_admin_css', plugin_dir_url(__FILE__) . 'admin-style.css', false, null);
    wp_enqueue_style('custom_wp_admin_css');
}
add_action('admin_enqueue_scripts', 'wpdocs_enqueue_custom_admin_style');
@ini_set('display_errors', 1);
@ini_set('max_execution_time', '300'); //300 seconds = 5 minutes
ini_set('memory_limit', '512M');
add_filter('http_request_args', 'tmy_http_request_args', 100, 1);
function tmy_http_request_args($r) //called on line 237
{
    $r['timeout'] = 15;
    return $r;
}

add_action('http_api_curl', 'tmy_http_api_curl', 100, 1);
function tmy_http_api_curl($handle) //called on line 1315
{
    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($handle, CURLOPT_TIMEOUT, 15);
}
include(plugin_dir_path(__FILE__) . 'class.pdf2text.php');
$max_file_download = get_option('tmy_open_ai_batch_size') ? get_option('tmy_open_ai_batch_size') : 5;
$max_tokens = get_option('tmy_max_tokens') ? intval(get_option('tmy_max_tokens')) : 100;

// if ($pagenow === 'options-general.php' && count($_GET) > 0 && $_GET['delete'] === "true") {
//     $allposts = get_posts(array('post_type' => 'post', 'numberposts' => -1));
//     foreach ($allposts as $eachpost) {
//         wp_delete_post($eachpost->ID, true);
//     }
//     $allarchives = get_posts(array('post_type' => 'archive', 'numberposts' => -1));
//     foreach ($allarchives as $eacharchive) {
//         wp_delete_post($eacharchive->ID, true);
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
    register_setting('tmy-option-group', 'tmy_max_tokens');
}
function tmy_markdown_plugin_setup_menu()
{
    add_menu_page('Markdown upload page', 'Markdown upload', 'manage_options', 'tmy_markdown-plugin', 'tmy_markdown_init');
}
function tmy_markdown_init()
{
    global $max_tokens;
    tmy_markdown_handle_post();
    global $max_file_download;
    $attachments = get_posts(array('post_type' => 'attachment', 'numberposts' => -1));
    //TODO remove me later
    //migrate media caption dropbox link to own meta field
    $allposts = get_posts(array('post_type' => 'archive', 'numberposts' => -1));
    foreach ($allposts as $eachpost) {
        $post_content_links = wp_extract_urls($eachpost->post_content);
        if (count($post_content_links) > 0) {
            foreach ($post_content_links as $post_content_link) {
                $existing_media_post = array_filter($attachments, function ($attachment) use ($post_content_link) {
                    return strpos($post_content_link, rawurlencode($attachment->post_title));
                });
                if (count($existing_media_post) > 0) {
                    $post = current($existing_media_post);
                    update_post_meta($post->ID, '_tmy_meta_key', $post_content_link);
                    $attachment = array(
                        'ID' => $post->ID,
                        'post_excerpt' => preg_match('/^https?:\/\/(www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~#?&\/\/=]*)$/', $post->post_excerpt) ? "" : $post->post_excerpt,
                        'post_content' => $post->post_content === "placeholder" ? "" : $post->post_content
                    );
                    $value = get_post_meta($post->ID, '_tmy_meta_custom_field_send_to_ai', true);
                    if ($value === "") {
                        update_post_meta($post->ID, '_tmy_meta_custom_field_send_to_ai', $post->post_content);
                    }
                    wp_update_post($attachment);
                }
            }
        }
    }
    $parsable_docs = array_filter($attachments, function ($attachment) {
        return $attachment->post_mime_type === "application/pdf" || $attachment->post_mime_type === "application/vnd.openxmlformats-officedocument.wordprocessingml.document";
    });
    $placeholders = array_filter($attachments, function ($attachment) {
        return $attachment->post_content == '' && ($attachment->post_mime_type === "application/pdf" || $attachment->post_mime_type === "application/vnd.openxmlformats-officedocument.wordprocessingml.document");
    });
    $media_with_just_link_excerpt = array_filter($parsable_docs, function ($attachment) {
        return $attachment->post_content !== "" && $attachment->post_excerpt === "";
    });
    ?>
    <h1>Import markdown file to archive post_type hierarchy</h1>

    <!-- Form to handle the upload - The enctype value here is very important -->
    <form method="post" action="options.php">
        <h2>Set OpenAI API key</h2>
        <?php settings_fields('tmy-option-group'); ?>
        <input type='text' class="regular-text" id="first_field_id" name="tmy_open_ai_api_key" value="<?php echo get_option('tmy_open_ai_api_key'); ?>">
        <h2>Set max batch size</h2>
        <input type='text' class="regular-text" id="second_field_id" name="tmy_open_ai_batch_size" value="<?php echo get_option('tmy_open_ai_batch_size'); ?>">
        <h2>Set max_tokens</h2>
        <input type='text' class="regular-text" id="third_field_id" name="tmy_max_tokens" value="<?php echo $max_tokens; ?>">
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
        <?php echo 'Total links found in all archive posts: ' . count($all_content_links); ?>
        <input type="hidden" name="download_files_from_content_links" value="true" />
        <?php submit_button('Batch download ' . $max_file_download . ' PDFs or docx') ?>
    </form>
    <form method="post" enctype="multipart/form-data">
        <h2>Extract text from PDFs and add to media description</h2>
        Total Media found: <?php echo count($attachments); ?>
        <br />
        Media type PDF or docx: <?php echo count($parsable_docs); ?>
        <br />
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
            <?php submit_button('Batch extract ' . $max_file_download . ' text from PDFs or docx') ?>
        <?php endif; ?>
    </form>
    <h2>Send Media description to OpenAi</h2>
    <?php echo 'PDFs or docx without summary: ' . count($media_with_just_link_excerpt) ?>:
    <br />
    <?php foreach ($media_with_just_link_excerpt as $key => $value) {
        echo '<a target="_blank" href="' . get_edit_post_link($value->ID) . '">' . $value->post_title . '</a><br/>';
    } ?>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="tmy_send_to_ai" value="true" />
        <?php submit_button('Batch summarize ' . $max_file_download . ' PDFs or docx') ?>
    </form>
    <br />
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
