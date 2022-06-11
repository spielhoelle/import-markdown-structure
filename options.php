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
include 'init.php';
function get_pdf_textcontent($id, $return = true)
{
    $post = get_post($id);
    $filepath = get_attached_file($post->ID);
    $text_content = "";
    // Parse pdf file and build necessary objects.
    $file_parts = pathinfo($filepath);
    if ($file_parts['extension'] === 'pdf') {
        $config = new \Smalot\PdfParser\Config();
        // $pdf = new Fpdi();
        // $pageCount = $pdf->setSourceFile($filepath);
        $file = pathinfo($filepath, PATHINFO_FILENAME);

        $pieces = explode("/", $filepath);
        array_pop($pieces);
        $directory = __DIR__ . '/tmp';

        $newPdf = new Fpdi();
        $pagecount = $newPdf->setSourceFile($filepath);
        $pagesToParse = $pagecount < 100 ? $pagecount : 100;
        for ($i = 1; $i <= $pagesToParse; $i++) {
            $newPdf->addPage();
            $newPdf->useTemplate($newPdf->importPage($i));
        }
        $newFilename = sprintf('%s/%s_%s.pdf', $directory, $file, "new");
        $newPdf->output($newFilename, 'F');

        $config->setFontSpaceLimit(-60);
        $config->setRetainImageContent(false);
        $config->setDecodeMemoryLimit(100000);
        $config->setHorizontalOffset('');
        $parser = new \Smalot\PdfParser\Parser([], $config);
        $raw_text = '';


        $tmp_file = $newFilename;
        $pdf    = $parser->parseFile($tmp_file);

        for ($i = 0; $i <= $pagesToParse; $i++) {
            if (!is_null($pdf->getPages()[$i])) {
                $page_text = $pdf->getPages()[$i]->getText() . "\n\r\n\r";
                $raw_text .= $page_text;
            }
        }

        // $biggest_page = 0;
        // foreach ($pages as $page) {
        //     if (strlen($page) > $biggest_page) {
        //         $biggest_page = strlen($page);
        //     }
        // }
        // foreach ($pages as $page) {
        //     preg_match('/[A-Z]{1}[a-z]*\s[a-z]*\s[a-z]*\s[a-z]*\s[a-z]*\s[^\.]*\./', $page, $indexOfFirstSentence);
        //     var_dump($indexOfFirstSentence);
        //     if (
        //         count($indexOfFirstSentence) > 0
        //         && strpos($page, "Copyright") === false
        //         && strpos($page, "copyright") === false
        //     ) {
        //         # if page has enough words
        //         // if (strlen($page) + ($biggest_page / 10) > $biggest_page) {
        //         // var_dump("#######################");
        //         $raw_text .= $page;
        //         // }
        //     }
        // }

        unlink($tmp_file); // delete file
        $text_content = $raw_text;
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
        $raw_text = strip_tags($content);
        $text_content = $raw_text;
    }
    $text_content  = preg_replace('/\s\s+/', ' ', $text_content);
    $text_content  = preg_replace('/\n+/', ' ', $text_content);
    $text_content = trim($text_content);
    $result = str_replace("\t", '', trim($text_content));
    // $result = substr($raw_text, 0 , 1000) . "\n\n" . $result;
    $postid = $post->ID;
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
                            'post_content'   => "",
                        );
                        $attachment_id = wp_insert_attachment($attachment, $new_file_path, $post_id);
                        update_post_meta($post_id, '_tmy_meta_key', $post_content_link);
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

function tmy_send_to_ai($id, $return = true)
{
    global $max_tokens;
    $value = get_post_meta($id, '_tmy_meta_ai_key', true);
    $post = get_post($id);
    
    return "FUCK";
    // $trimmed = trim(preg_replace('/\s\s+/', ' ', $value));
    // $trimmed = preg_replace('/\d{1,2}\s{1}/', "", $trimmed);
    // preg_match('/(?!.*copyright)[A-Z]{1}[a-z]*\s\w*,?\s\w*,?\s\w*,?\s\w*,?\s[^\.]*\.\s/', $trimmed, $indexOfFirstSentence, PREG_OFFSET_CAPTURE);
    // $shortened = substr($trimmed, $indexOfFirstSentence && $indexOfFirstSentence[0] ? $indexOfFirstSentence[0][1] : 0, 100000 - $max_tokens);
    $tldr = $value . "\n\ntl;dr:\n";
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
    if (!isset($json->choices)) {
        var_dump($json);
    } else {
        foreach ($json->choices as $choice) {
            $post_data .= $choice->text . "\n\r\n\r";
        }
        $data = array(
            'ID' => $id,
            'post_excerpt' => $post_data
        );
        $res = wp_update_post($data, true);
    }
    if ($return) {
        echo trim($post_data);
        die;
    }
}
