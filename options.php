<?php
/*
Plugin Name: Import markdown structure to posts or pages
Plugin URI: https://tmy.io
Description: Able to import markdown syntax and automatically assign parent-child categories
Version: 1
Author: Thomas Kuhnert
Author URI: https://tmy.io
*/

// Wp v4.7.1 and higher
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
        </style>';
}
add_action('admin_head', 'fix_svg');

add_action('admin_menu', 'tmy_markdown_plugin_setup_menu');
function tmy_markdown_plugin_setup_menu()
{
    add_menu_page('Markdown upload page', 'Markdown upload', 'manage_options', 'tmy_markdown-plugin', 'tmy_markdown_init');
}

function tmy_markdown_init()
{
    tmy_markdown_handle_post();

?>
    <h1>Hello World!</h1>
    <h2>Upload a File</h2>
    <!-- Form to handle the upload - The enctype value here is very important -->
    <form method="post" enctype="multipart/form-data">
        <input type='file' id='tmy_markdown_upload_pdf' name='tmy_markdown_upload_pdf'></input>
        <?php submit_button('Upload') ?>
    </form>
<?php
}

function tmy_markdown_handle_post()
{
    // First check if the file appears on the _FILES array
    if (isset($_FILES['tmy_markdown_upload_pdf'])) {
        $pdf = $_FILES['tmy_markdown_upload_pdf'];

        // Use the wordpress function to upload
        // tmy_markdown_upload_pdf corresponds to the position in the $_FILES array
        // 0 means the content is not associated with any other posts
        $uploaded = media_handle_upload('tmy_markdown_upload_pdf', 0);
        // Error checking using WP functions
        if (is_wp_error($uploaded)) {
            echo "Error uploading file: " . $uploaded->get_error_message();
        } else {
            echo "File upload, import of category structure and post-creation successful!";
            test_convert($uploaded);
        }
    }
}

function test_convert($id)
{
    $allposts = get_posts(array('post_type' => 'post', 'numberposts' => -1));
    foreach ($allposts as $eachpost) {
        wp_delete_post($eachpost->ID, true);
    }
    $args = array(
        "hide_empty" => 0,
        "type"       => "post",
        "orderby"    => "name",
        "order"      => "ASC"
    );
    $types = get_categories($args);
    foreach ($types as $type) {
        wp_delete_category($type->ID);
    }
    $file = get_attached_file($id);
    $file_array = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $id = 0;
    $id_sub = 0;
    $id_sub_sub = 0;
    for ($i = 0; $i < count($file_array); $i++) {
        if (strpos($file_array[$i], "#") === 0 && $file_array[$i][1] !== "#") {
            // echo "<h2>" . $file_array[$i] . "</h2>";
            $id = wp_create_category(substr($file_array[$i], 1), 0);
        } else if ($file_array[$i][0] === "#" && $file_array[$i][1] === "#" && $file_array[$i][2] !== "#") {
            $id_sub = wp_create_category(substr($file_array[$i], 2), $id);
            // echo "<h3>" . $file_array[$i] . "</h3>";
        } else if ($file_array[$i][0] === "#" && $file_array[$i][1] === "#" && $file_array[$i][2] === "#") {
            $id_sub_sub = wp_create_category(substr($file_array[$i], 3), $id_sub);
            // echo "<h4>" . $file_array[$i] . "</h4>";
        } else {
            // echo $file_array[$i];

            preg_match_all("/\[.*\]/", substr($file_array[$i], 2), $matches);
            preg_match_all("/\(.*\)/", substr($file_array[$i], 2), $matches2);

            $my_post = array(
                'post_title'    => $matches[0] ? substr($matches[0][0], 1, -1) : substr($file_array[$i], 2),
                'post_content'  => $matches2[0] ? substr($matches2[0][0], 1, -1) : substr($file_array[$i], 2),
                'post_status'   => 'publish',
                'post_author'   => 1,
                'post_category' => array($id, $id_sub, $id_sub_sub),
            );
            wp_insert_post($my_post);
            echo "<br/>";
        }
    }

}
?>