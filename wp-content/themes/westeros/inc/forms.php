<?php
/** Presentation repairs for the two existing Persian Contact Form 7 forms. */
defined('ABSPATH') || exit;

function westeros_is_legacy_contact_form($id) {
    return in_array((int)$id,array(165,9494),true);
}

function westeros_contact_field_id($form,$name) {
    $unit=$form->unit_tag();
    if (!$unit && isset($_POST['_wpcf7_unit_tag']) && is_string($_POST['_wpcf7_unit_tag'])) { $unit=wp_unslash($_POST['_wpcf7_unit_tag']); }
    if (!is_string($unit) || !preg_match('/^wpcf7-f'.(int)$form->id().'-(?:p[0-9]+-)?o[0-9]+$/D',$unit)) { $unit='form-'.$form->id(); }
    return 'ws-contact-'.$unit.'-'.$name;
}

// Use CF7's native tag IDs so its validation summary can link back to the fields.
add_filter('wpcf7_form_tag',function($tag){
    $form=WPCF7_ContactForm::get_current();
    $field=new WPCF7_FormTag($tag);
    if (!$form || !westeros_is_legacy_contact_form($form->id()) || !in_array($field->name,array('your-name','your-email','your-subject','your-message'),true)) { return $tag; }
    if (!$field->get_option('id','id',true)) { $field->options[]='id:'.westeros_contact_field_id($form,$field->name); }
    return $field;
});

add_filter('wpcf7_form_elements',function($html){
    $form=WPCF7_ContactForm::get_current();
    if (!$form || !westeros_is_legacy_contact_form($form->id())) { return $html; }
    // These legacy templates put a plain-text label in the paragraph before its control.
    // Only that exact shape is repaired; authored labels and other forms are retained.
    return preg_replace_callback('~<p>([^<>]+)</p>(\s*)<p>(<span\b[^>]*\bdata-name=["\'](your-name|your-email|your-subject|your-message)["\'][^>]*>)(<(?:input|textarea)\b[^>]*>)~i',static function($match)use($form){
        if (trim(html_entity_decode($match[1],ENT_QUOTES,'UTF-8'))==='') { return $match[0]; }
        // The HTML processor needs a complete raw-text element to visit textarea.
        $suffix=str_starts_with(strtolower($match[5]),'<textarea')?'</textarea>':'';
        $control=new WP_HTML_Tag_Processor($match[5].$suffix);
        if (!$control->next_tag() || $control->get_attribute('name')!==$match[4]) { return $match[0]; }
        $id=$control->get_attribute('id');
        if (!is_string($id) || $id==='') { $id=westeros_contact_field_id($form,$match[4]); $control->set_attribute('id',$id); }
        $autocomplete=array('your-name'=>'name','your-email'=>'email');
        if (isset($autocomplete[$match[4]]) && $control->get_attribute('autocomplete')===null) { $control->set_attribute('autocomplete',$autocomplete[$match[4]]); }
        $opening=$control->get_updated_html();
        if ($suffix) { $opening=substr($opening,0,-strlen($suffix)); }
        return '<p><label for="'.esc_attr($id).'">'.$match[1].'</label></p>'.$match[2].'<p>'.$match[3].$opening;
    },$html);
});

add_filter('do_shortcode_tag',function($html,$tag){
    if ($tag!=='contact-form-7') { return $html; }
    $processor=new WP_HTML_Tag_Processor($html);
    while ($processor->next_tag(array('tag_name'=>'DIV','class_name'=>'wpcf7'))) {
        $id=$processor->get_attribute('data-wpcf7-id');
        if (!westeros_is_legacy_contact_form($id)) { continue; }
        $processor->set_attribute('lang','fa-IR'); $processor->set_attribute('dir','rtl');
        $form=WPCF7_ContactForm::get_current();
        $title=$form && (int)$form->id()===(int)$id?$form->title():get_post_field('post_title',(int)$id,'raw');
        if ($title && $processor->next_tag('FORM')) { $processor->set_attribute('aria-label',$title); }
        break;
    }
    return $processor->get_updated_html();
},15,2);
