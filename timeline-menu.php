<?php
/**
 * Plugin Name: timeline-menu
 * Plugin URI: https://github.com/RepComm/wp-timeline-menu
 * Description: A timeline nav for posts belonging to specific timelines
 * Author: Jonathan Crowder
 * Author URI: https://github.com/RepComm
 * License: The Unlicense
 * Version: 1.0
 */

function get_category_by_name ($name) {
  $cats = get_categories();
  foreach ($cats as $cat) {
    if ($cat->name == $name) {
      return $cat;
    }
  }
  return null;
}

function _get_category_children ($cat) {
  $category_args = array(
    'parent'     => $cat->term_id,
    'hierarchical' => true,
    'hide_empty'   => false
  );
  return get_categories ($category_args);
}

/**Walk a category tree
 * @param object $parent category to parse all children of
 * @param int $maxDepth is the maximum
 * @param callback $cb function that is called, first argument is the category child
 */
function recurse_category_display ($parent, $cb, $maxDepth=10, $currentDepth = 0) {
  if ($maxDepth != -1 && $currentDepth > $maxDepth) return;
  //$cb($parent, $currentDepth);
  call_user_func ($cb, $parent, $currentDepth);
  $children = _get_category_children($parent);
  foreach ($children as $child) {
    recurse_category_display($child, $cb, $maxDepth, $currentDepth + 1);
  }
}

function create_category_item ($category, $depth) {
  echo "<span style='padding-left:" . $depth . "em;'>" . $category->name . "</span><br/>";

  $posts = _get_category_posts($category);

  foreach ($posts as $post) {
    echo "<a href='" . get_permalink($post) . "' style='padding-left:" . ($depth+1) . "em;'>";
    echo "<span>" . $post->post_title . "</span>";
    echo "</a><br/>";
  }
}

function _get_category_posts($category, $maxPosts = 32) {
  $get_post_args = array(
    'numberposts' => $maxPosts,
    'category__in' => $category->term_id
  );
  return get_posts ($get_post_args);
}

class TimelineWidget extends WP_Widget {
  public function __construct () {
    //process
    parent::__construct (
      "TimelineWidget",
      "TimelineWidget",
      array ("description" => __("A timeline widget", "text_domain") )
    );
  }
  public function widget ($args, $instance) {
    // ---- Display widget content

    extract($args);
    //get the title
    $title = apply_filters("widget_title", $instance["title"]);
    //get the category tree walk depth
    $maxCatDepth = $instance["maxcatdepth"];

    //get the category tree root
    $rootCatName = $instance["cat"];
    $rootCat = get_category_by_name($rootCatName);

    //Check if the category selected is valid
    if (!isset($rootCat)) {
      //TODO - sanitize name
      echo "<span>Timeline root category is not set to a valid category, found" . $rootCatName . "</span>";
      return;
    }

    //show the wordpress after/before junk so things render right
    echo $before_widget;
    if (!empty($title)) {
      echo $before_title . $title . $after_title;
    }

    //walk the category tree started at our selected root category
    recurse_category_display($rootCat, "create_category_item", $maxCatDepth);

    //TODO
    // get_posts ();

    echo $after_widget;
  }
  public function form ($instance) {
    // ---- options in the editor

    if (isset($instance["title"])) {
      $title = $instance["title"];
    } else {
      $title = __("New Title", "text_domain");
    }

    if (isset($instance["cat"])) {
      $cat = $instance["cat"];
    } else {
      $cat = __("Category", "text_domain");
    }

    if (isset($instance["maxcatdepth"])) {
      $maxcatdepth = $instance["maxcatdepth"];
    } else {
      $maxcatdepth = __("10", "number_domain");
    }

    // ---- Title editor
    $nameTitle = $this->get_field_name("title");
    $idTitle = $this->get_field_id("title");
    $valueTitle = esc_html($title);
    $labelTitle = "<label for='" . $nameTitle . "'>Title:</label>";
    $inputTitle = "<input class='widefat' id='" . $idTitle . "' name='" . $nameTitle . "' type='text' value='" . $valueTitle . "' />";

    // ---- Root category selector
    $nameTopCat = $this->get_field_name("cat");
    $idTopCat = $this->get_field_id("cat");

    $labelTopCat = "<label for='" . $nameTopCat . "'>Root Category:</label>";
    $selectTopCat = "<select class='widefat' id='" . $idTopCat . "' name='" . $nameTopCat . "' >";

    $category_args = array(
      'hide_empty'   => false
    );
    $cats = get_categories($category_args);

    foreach ($cats as $catopt) {
      if ($catopt->name == $cat) {
        $selectTopCat .= "<option selected value='" . $catopt->name . "'>" . $catopt->name . "</option>";  
      } else {
        $selectTopCat .= "<option value='" . $catopt->name . "'>" . $catopt->name . "</option>";
      }
    }
    $selectTopCat .= "</select>";

    // ---- Max Category depth editor
    $nameMaxCatDepth = $this->get_field_name("maxcatdepth");
    $idMaxCatDepth = $this->get_field_id("maxcatdepth");
    $valueMaxCatDepth = esc_html($maxcatdepth);
    $labelMaxCatDepth = "<label for='" . $nameMaxCatDepth . "'>Max Category Depth:</label>";
    $inputMaxCatDepth = "<input class='widefat' id='" . $idMaxCatDepth . "' name='" . $nameMaxCatDepth . "' type='number' value='" . $valueMaxCatDepth . "' />";

    echo "<p>" . $labelTitle . $inputTitle . $labelTopCat . $selectTopCat . $labelMaxCatDepth . $inputMaxCatDepth . "</p>";
  }
  public function update ($new, $old) {
    $instance = array();
    $instance["title"] = ( !empty($new["title"])) ? strip_tags($new["title"]) : '';

    $instance["cat"] = ( !empty($new["cat"])) ? strip_tags($new["cat"]) : '';

    $instance["maxcatdepth"] = ( !empty($new["maxcatdepth"])) ? strip_tags($new["maxcatdepth"]) : "10";

    return $instance;
  }
}
add_action( 'widgets_init', 'init_widgets' );
 
function init_widgets() {
  register_widget( 'TimelineWidget');
}

class TimelineMenu {
  static function install() {
    //do not generate any output here
  }
  static function uninstall () {

  }
}
register_activation_hook( __FILE__, array("TimelineMenu", "install"));
register_uninstall_hook(__FILE__, array("TimelineMenu", "uninstall"));

/**Registers the on_load function in the current file*/
function on_load() {
  if ( is_admin() && get_option( 'Activated_Plugin' ) == 'Plugin-Slug' ) {
    delete_option( 'Activated_Plugin' );

    
  }
}
add_action( 'admin_init', 'on_load' );

?>
