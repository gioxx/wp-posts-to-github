<?php
// tests/wp-stubs.php

class WP_Post
{
    public $ID;
    public $post_name;
    public $post_content;
    public $post_date_gmt;
    public $post_modified_gmt;
    public $post_status = 'publish';
    public $post_type = 'post';
}
