<?php
/*
Plugin Name: Post Rating, Featured Image, and Top Rated Posts Plugin
Description: Adds rating functionality, displays featured image in WordPress single posts, and includes a widget to show top-rated posts.
Version: 1.0.0
Author: Rahul Biswas
*/

// Add ratings functionality
function add_ratings() {
    $rating_html = '';

    if (is_single()) {
        $post_id = get_the_ID();
        $rating = get_post_meta($post_id, 'rating', true);

        if ($rating) {
            $rating_html = '<div class="rating" style="font-weight: bold; margin-bottom: 10px;">' . esc_html__('Rating:', 'post-rating-plugin') . ' ' . get_rating_stars($rating) . '</div>';
        }
    }

    echo $rating_html;
}
add_action('astra_entry_content_before', 'add_ratings');

// Add ratings meta box
function add_ratings_meta_box() {
    add_meta_box('ratings_meta_box', 'Post Rating', 'render_ratings_meta_box', 'post', 'side', 'default');
}
add_action('add_meta_boxes', 'add_ratings_meta_box');

// Render ratings meta box
function render_ratings_meta_box($post) {
    $rating = get_post_meta($post->ID, 'rating', true);
    wp_nonce_field('ratings_meta_box', 'ratings_meta_box_nonce');
    ?>
    <label for="rating"><?php esc_html_e('Rating:', 'post-rating-plugin'); ?></label>
    <input type="number" step="0.1" min="0" max="5" id="rating" name="rating" value="<?php echo esc_attr($rating); ?>" />
    <?php
}

// Save ratings meta box data
function save_ratings_meta_box_data($post_id) {
    if (!isset($_POST['ratings_meta_box_nonce']) || !wp_verify_nonce($_POST['ratings_meta_box_nonce'], 'ratings_meta_box')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['rating'])) {
        $rating = floatval($_POST['rating']);
        update_post_meta($post_id, 'rating', $rating);
    }
}
add_action('save_post', 'save_ratings_meta_box_data');

// Display featured image in single posts
function display_featured_image() {
    if (is_single()) {
        $post_id = get_the_ID();
        if (has_post_thumbnail($post_id)) {
            $thumbnail_url = get_the_post_thumbnail_url($post_id, 'full');
            echo '<img src="' . esc_url($thumbnail_url) . '" alt="Featured Image" class="single-post-featured-image" />';
        }
    }
}
add_action('astra_entry_top', 'display_featured_image');

// Top Rated Posts Widget
class Top_Rated_Posts_Widget extends WP_Widget {

    public function __construct() {
        $widget_ops = array(
            'classname' => 'top-rated-posts-widget',
            'description' => 'Displays the top-rated posts on the sidebar or footer.'
        );
        parent::__construct('top_rated_posts_widget', 'Top Rated Posts', $widget_ops);
    }

    public function widget($args, $instance) {
        $title = apply_filters('widget_title', $instance['title']);
        $number_of_posts = isset($instance['number_of_posts']) ? absint($instance['number_of_posts']) : 5;

        echo $args['before_widget'];

        if ($title) {
            echo $args['before_title'] . $title . $args['after_title'];
        }

        $query_args = array(
            'post_type' => 'post',
            'meta_key' => 'rating',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
            'posts_per_page' => $number_of_posts
        );

        $top_rated_posts = new WP_Query($query_args);

        if ($top_rated_posts->have_posts()) {
            echo '<ul>';

            while ($top_rated_posts->have_posts()) {
                $top_rated_posts->the_post();
                $rating = get_post_meta(get_the_ID(), 'rating', true);
                echo '<li><a href="' . get_permalink() . '">' . get_the_title() . '</a> ' . get_rating_stars($rating) . '</li>';
            }

            echo '</ul>';
        }

        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = isset($instance['title']) ? esc_attr($instance['title']) : '';
        $number_of_posts = isset($instance['number_of_posts']) ? absint($instance['number_of_posts']) : 5;
        ?>

        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php echo esc_html('Title:'); ?></label>
            <input class="widefat" type="text" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $title; ?>">
        </p>

        <p>
            <label for="<?php echo $this->get_field_id('number_of_posts'); ?>"><?php echo esc_html('Number of Posts to Display:'); ?></label>
            <input class="widefat" type="number" id="<?php echo $this->get_field_id('number_of_posts'); ?>" name="<?php echo $this->get_field_name('number_of_posts'); ?>" value="<?php echo $number_of_posts; ?>">
        </p>

        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = sanitize_text_field($new_instance['title']);
        $instance['number_of_posts'] = absint($new_instance['number_of_posts']);

        return $instance;
    }
}

function register_top_rated_posts_widget() {
    register_widget('Top_Rated_Posts_Widget');
}
add_action('widgets_init', 'register_top_rated_posts_widget');

// Helper function to generate rating stars SVG
function get_rating_stars($rating) {
    $filled_star = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#ffc107" width="18px" height="18px"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>';
    $half_star = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#ffc107" width="18px" height="18px"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M5.82 21l1.64-7.03L2 9.24l7.19-.61L12 2l2.81 6.63 7.19.61-5.46 4.73 1.64 7.03L12 17.27V21h-1.64z"/></svg>';
    $empty_star = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#ccc" width="18px" height="18px"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M0 0h24v24H0V0z" fill="none"/></svg>';

    $stars_html = '';

    $filled_stars = floor($rating);
    $has_half_star = ($rating - $filled_stars) >= 0.5;
    $empty_stars = 5 - $filled_stars - ($has_half_star ? 1 : 0);

    for ($i = 0; $i < $filled_stars; $i++) {
        $stars_html .= $filled_star;
    }

    if ($has_half_star) {
        $stars_html .= $half_star;
    }

    for ($i = 0; $i < $empty_stars; $i++) {
        $stars_html .= $empty_star;
    }

    return $stars_html;
}
