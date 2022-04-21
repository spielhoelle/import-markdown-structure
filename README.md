# Import markdown to post/category structure

Wordpress plugin that parses a markdown file, scraps dropbox links and put them into a custom post type category hierarchy. Those posts can download PDFs/Docx which get attached to the post. The content of those files can be fetched by a PDF/docx parser and then send to to OpenAI for a summary.

## Usage
1. Upload activate plugin
1. Click left bottom on `Markdown upload`
1. Select file and click `Upload`

You have now categories per # ## ### hierarchy and the archive-posts assigned to it. Links will be deconstructed to post title and post content.


Use the `Markdown upload` [options page](https://toshodex.com/wp-admin/admin.php?page=tmy_markdown-plugin) to parse and summarize the pdf/docx.  

Also inside the [edit-media](https://toshodex.com/wp-admin/post.php?post=690&action=edit) itself in the sidebar under `Save PDF content to Mediacaption` its possible to scrape the pdf/docx content and summarize a single file.



## Development
Install composer  
`curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer`

Go in folder...  
`cd import-markdown-structure` . 

Install/update pdfparser
`composer require smalot/pdfparser` . 

