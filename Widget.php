<?php

/**
 * Sidebar LoginLogout widget with Facebook Connect button
 * */
class Widget_LoginLogout extends WP_Widget {

    //////////////////////////////////////////////////////
    //Init the Widget
    function Widget_LoginLogout() {
        $this->WP_Widget(false, "WP-FB AutoConnect", array('description' => 'A sidebar Login/Logout form with Facebook Connect button'));
    }

    //////////////////////////////////////////////////////
    //Output the widget's content.
    function widget($args, $instance) {        
        //Get args and output the title
        extract($args);
        echo $before_widget;
        $title = apply_filters('widget_title', $instance['title']);
        if ($title)
            echo $before_title . $title . $after_title;
        init_button();
?>
<?php
                    echo $after_widget;
                }

                //////////////////////////////////////////////////////
                //Update the widget settings
                function update($new_instance, $old_instance) {
                    $instance = $old_instance;
                    $instance['title'] = $new_instance['title'];
                    return $instance;
                }

                ////////////////////////////////////////////////////
                //Display the widget settings on the widgets admin panel
                function form($instance) {
?>
                    <p>
                        <label for="<?php echo $this->get_field_id('title'); ?>"><?php echo 'Title:'; ?></label>
                        <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $instance['title']; ?>" />
                    </p>
<?php
                }

            }

//Register the widget
            add_action('widgets_init', 'register_jfbLogin');

            function register_jfbLogin() {
                register_widget('Widget_LoginLogout');
            }
?>