<?php
add_action( 'widgets_init', 'tie_soundcloud_widget' );
function tie_soundcloud_widget() {
	register_widget( 'tie_soundcloud' );
}
class tie_soundcloud extends WP_Widget {

	public function __construct(){
		$widget_ops = array( 'classname' => 'tie-soundcloud'  );
		$control_ops = array( 'width' => 250, 'height' => 350, 'id_base' => 'tie-soundcloud-widget' );
		parent::__construct( 'tie-soundcloud-widget',theme_name .' - SoundCloud', $widget_ops, $control_ops );
	}

	public function widget( $args, $instance ) {
		extract( $args );
		$title = apply_filters( 'widget_title', empty( $instance['text_html_title'] ) ? '' : $instance['text_html_title'], $instance, $this->id_base );
		$url = $instance['url'];
		$autoplay = $instance['autoplay'];

		$play = 'false';
		if( !empty( $autoplay )) $play = 'true';



			echo $before_widget;
			echo $before_title;
			echo $title ;
			echo $after_title;
			echo tie_soundcloud( $url , $play );
			echo $after_widget;

	}

	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['text_html_title'] = strip_tags( $new_instance['text_html_title'] );
		$instance['url'] = $new_instance['url'] ;
		$instance['autoplay'] = strip_tags( $new_instance['autoplay'] );
		return $instance;
	}

	public function form( $instance ) {
		$defaults = array( 'text_html_title' => 'SoundCloud'  );
		$instance = wp_parse_args( (array) $instance, $defaults ); ?>

		<p>
			<label for="<?php echo $this->get_field_id( 'text_html_title' ); ?>">Title : </label>
			<input id="<?php echo $this->get_field_id( 'text_html_title' ); ?>" name="<?php echo $this->get_field_name( 'text_html_title' ); ?>" value="<?php if( !empty($instance['text_html_title']) ) echo $instance['text_html_title']; ?>" class="widefat" type="text" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'url' ); ?>">URL :</label>
			<input id="<?php echo $this->get_field_id( 'url' ); ?>" name="<?php echo $this->get_field_name( 'url' ); ?>" value="<?php if( !empty($instance['url']) ) echo $instance['url']; ?>" type="text" class="widefat" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'autoplay' ); ?>">Autoplay :</label>
			<input id="<?php echo $this->get_field_id( 'autoplay' ); ?>" name="<?php echo $this->get_field_name( 'autoplay' ); ?>" value="true" <?php if( !empty($instance['autoplay']) ) echo 'checked="checked"'; ?> type="checkbox" />
		</p>


	<?php
	}
}
?>