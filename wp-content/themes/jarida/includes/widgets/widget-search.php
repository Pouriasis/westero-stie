<?php
add_action( 'widgets_init', 'search_widget' );
function search_widget() {
	register_widget( 'search' );
}
class search extends WP_Widget {

	public function __construct(){
		$widget_ops = array( 'classname' => 'search'  );
		$control_ops = array( 'width' => 250, 'height' => 350, 'id_base' => 'search-widget' );
		parent::__construct( 'search-widget',theme_name .' - Search', $widget_ops, $control_ops );
	}

	public function widget( $args, $instance ) { ?>


	<div class="search-widget">
		<form method="get" id="searchform-widget" action="<?php echo home_url() ; ?>/">
			<input type="text" id="s" name="s" value="<?php _e( 'to search type and hit enter' , 'tie' ) ?>" onfocus="if (this.value == '<?php _e( 'to search type and hit enter' , 'tie' ) ?>') {this.value = '';}" onblur="if (this.value == '') {this.value = '<?php _e( 'to search type and hit enter' , 'tie' ) ?>';}"  />
		</form>
	</div><!-- .search-widget /-->
<?php
	}
}
?>