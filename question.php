<?php

require 'includes.php';
/*
if(!IsLoggedIn()){
	Leave('signin.php');
}

if(!AdminCanManageSurvey()){
	if(AdminCanManageAdmins()){
		Leave('admins.php');
	}else{
		Leave('signin.php');
	}
}
*/
if(isset($_GET['id'])){
	$id = intval($_GET['id']);
}else{
	Leave('index.php');
}

$question = $db->get_row("SELECT * FROM " . TABLES_PREFIX . "question WHERE id = $id ORDER BY id DESC LIMIT 0,1");

$layout = PrivatePage('question',  $db->get_var("SELECT title FROM " . TABLES_PREFIX . "survey WHERE id = " . intval($question->survey_id)) . ' - {{ST:manage_questions}}');

if(isset($_GET['delete_file']) AND intval($_GET['delete_file']) != 0){
	$file_to_delete = '';
	$files = unserialize($question->attachment);
	$new_files = array();
	if(count($files) > 0){
		$files_count = 1;
		foreach($files as $f){
			if(intval($_GET['delete_file']) == $files_count){
				$file_to_delete = $f;
			}else{
				$new_files[] = $f;
			}
			$files_count++;
		}
		
		if($file_to_delete){
			$file = 'uploads/' . $file_to_delete;
			$exists = file_exists($file);
			if($exists){
				unlink($file);
				$db->update(TABLES_PREFIX . "question", array('attachment'=>serialize($new_files)), array('id'=>$id), array("%s"));
			
				Leave('question.php?id='.$id.'&message=file_deleted');
			}else{
				$layout->AddContentById('alert', $layout->GetContent('alert'));
				$layout->AddContentById('alert_nature', ' alert-danger');
				$layout->AddContentById('alert_heading', '{{ST:error}}!');
				$layout->AddContentById('alert_message', '{{ST:file_does_not_exist}}');
			}
		}else{
			$layout->AddContentById('alert', $layout->GetContent('alert'));
			$layout->AddContentById('alert_nature', ' alert-danger');
			$layout->AddContentById('alert_heading', '{{ST:error}}!');
			$layout->AddContentById('alert_message', '{{ST:file_does_not_exist}}');
		}
	}
}

if(isset($_GET['message']) AND $_GET['message'] != ''){
	if($_GET['message'] == 'file_deleted'){
		$layout->AddContentById('alert', $layout->GetContent('alert'));
		$layout->AddContentById('alert_nature', ' alert-success');
		$layout->AddContentById('alert_heading', '{{ST:success}}!');
		$layout->AddContentById('alert_message', '{{ST:the_file_has_been_deleted}}');
	}
}

if(isset($_POST['delete'])){
	$db->query("DELETE FROM " . TABLES_PREFIX . "question WHERE id = " . $id);
	$db->query("DELETE FROM " . TABLES_PREFIX . "choices WHERE question_id = " . $id);
	Leave('questions.php?id=' . $question->survey_id . '&message=deleted');
}

if(isset($_GET['delete_choice']) AND $_GET['delete_choice'] != ''){
	$db->query("DELETE FROM " . TABLES_PREFIX . "choices WHERE id = " . intval($_GET['delete_choice']));
	$layout->AddContentById('alert', $layout->GetContent('alert'));
	$layout->AddContentById('alert_nature', ' alert-success');
	$layout->AddContentById('alert_heading', '{{ST:success}}!');
	$layout->AddContentById('alert_message', '{{ST:the_choice_has_been_deleted}}');
}

$layout->AddContentById('id', $question->id);
$layout->AddContentById('survey_id', $question->survey_id);

if(isset($_POST['submit'])){
	
	$errors = false;
	$values = array();
	$format = array();
	$error_msg = '';
	
	$choices = array();
	
	if(isset($_POST['question']) AND $_POST['question'] != ''){
		$layout->AddContentById('question', $_POST['question']);
		$values['question'] = Clean($_POST['question']);
		$format[] = "%s";
	}else{
		$errors = true;
		$error_msg .= '{{ST:question_required}} ';
	}

	if(isset($_POST['order_by']) AND intval($_POST['order_by']) != 0){
		$layout->AddContentById('order_by', $_POST['order_by']);
		$values['order_by'] = intval($_POST['order_by']);
		$format[] = "%d";
	}else{
		$values['order_by'] = 9999;
		$format[] = "%d";
	}
	
	if(isset($_POST['question_type']) AND $_POST['question_type'] != ''){
		$layout->AddContentById('selected_type_' . $_POST['question_type'], 'selected');
		$values['question_type'] = Clean($_POST['question_type']);
		$format[] = "%s";
		
		
		if($_POST['question_type'] == 'mp' OR $_POST['question_type'] == 'ma' OR $question->question_type == 'dd'){
			if(isset($_POST['choice']) AND count($_POST['choice']) > 0){
				foreach($_POST['choice'] as $k => $choice){
					if($choice != ''){
						$choices[$k] = $choice;
						$layout->AddContentById('choices', '<input type="text" class="form-control" name="choice['.$k.']" value="'.$choice.'" placeholder="{{ST:choice}}"><br/><br/>{{ID:choices}}');
					}
				}
				
				if(count($choices) == 0){
					$errors = true;
					$error_msg .= '{{ST:choices_are_required}} ';
				}else{
					//$values['choices'] = serialize($choices);
					//$format[] = "%s";
				}
			}else{
				$errors = true;
				$error_msg .= '{{ST:choices_are_required}} ';
			}
		}else{
			$layout->AddContentById('hide_multi', 'style="display:none;"');
			$layout->AddContentById('choices', '<input type="text" class="form-control" name="choice[]" value="" placeholder="{{ST:choice}}"><br/><br/>');
		}
	}else{
		$errors = true;
		$error_msg .= '{{ST:question_type_required}} ';
		$layout->AddContentById('hide_multi', 'style="display:none;"');
		$layout->AddContentById('choices', '<input type="text" class="form-control" name="choice[]" value="" placeholder="{{ST:choice}}"><br/><br/>');
	}
	
	$files = array();
	$files = unserialize($question->attachment);
	$files_temp = array();
	$uploads = getNormalizedFILES();
	if(isset($uploads["files"])  AND count($uploads["files"]) > 0){
		
		foreach($uploads["files"] as $u){
			if(isset($u["name"]) AND $u["name"] != ''){
				if($u["error"] > 0){
					$errors = true;
					$error_msg .= $u["name"] . ' ' . UploadError($u["error"]) . '. ';
				}else{
					$filename = set_filename('uploads/', clean_file_name($u["name"]));
					$files[] = $filename;
					$files_temp[] = array('name'=>$filename, 'temp'=>$u["tmp_name"]);
				}
			}
		}
		
		
		
		
		
	}
	$values['attachment'] = serialize($files);
	$format[] = "%s";
	
	
	if(isset($_POST['is_required'])){
		$layout->AddContentById('is_required_state', 'checked="checked"');
		$values['is_required'] = 'y';
		$format[] = "%s";
	}else{
		$values['is_required'] = 'n';
		$format[] = "%s";
	}
	
	if(!$errors){
		$db->update(TABLES_PREFIX . "question", $values, array('id'=>$id), $format);
		
		if(count($files_temp) > 0){
			foreach($files_temp as $temp){
				move_uploaded_file($temp["temp"], 'uploads/' . $temp["name"]);
			}
		}
		
		if(count($files) > 0 AND is_array($files)){
			$files_lists = '<ol>';
			$files_count = 1;
			foreach($files as $f){
				if(is_image_file($f)){
					$files_lists .= '<li><a target="_blank" href="'. BASE_URL . 'uploads/' . $f.'" rel="prettyPhoto[gal]">{{ST:attachment}} '.$files_count.'</a> - <a onclick="return confirm(\'{{ST:are_you_sure}}\');" href="question.php?id='.$id.'&delete_file='.$files_count.'">Delete</a></li>';
				}else{
					$files_lists .= '<li><a target="_blank" href="'. BASE_URL . 'uploads/' . $f.'">{{ST:attachment}} '.$files_count.'</a> - <a onclick="return confirm(\'{{ST:are_you_sure}}\');" href="question.php?id='.$id.'&delete_file='.$files_count.'">Delete</a></li>';
				}
				$files_count++;
			}
			$files_lists .= '</ol>';
			$layout->AddContentById('files', $files_lists);
		}
		
		if(count($choices) > 0){
			$choice_ids = array();
			
			foreach($choices as $k => $c){
				if(substr($k,0,3) == "id_"){
					$db->update(TABLES_PREFIX . "choices", array('choice'=>$c), array('id'=>str_replace("id_","",$k)), array("%s"));
					$choice_ids[] = str_replace("id_","",$k);
				}else{
					$db->insert(TABLES_PREFIX . "choices", array('survey_id'=>$question->survey_id,'question_id'=>$id,'choice'=>$c), array("%d","%d","%s"));
					$choice_ids[] = $db->insert_id;
				}
			}
		}
		
		$layout->AddContentById('alert', $layout->GetContent('alert'));
		$layout->AddContentById('alert_nature', ' alert-success');
		$layout->AddContentById('alert_heading', '{{ST:success}}!');
		$layout->AddContentById('alert_message', '{{ST:the_item_has_been_saved}}');
	}else{
		$layout->AddContentById('alert', $layout->GetContent('alert'));
		$layout->AddContentById('alert_nature', ' alert-danger');
		$layout->AddContentById('alert_heading', '{{ST:error}}!');
		$layout->AddContentById('alert_message', $error_msg);
	}
	
}else{
	
	$layout->AddContentById('question', $question->question);
	
	if(intval($question->order_by) != 9999){
		$layout->AddContentById('order_by', intval($question->order_by));
	}
	
	$layout->AddContentById('selected_type_' . $question->question_type, 'selected');
	if($question->question_type == 'mp' OR $question->question_type == 'ma' OR $question->question_type == 'dd'){
		$choices = $db->get_results("SELECT * FROM " . TABLES_PREFIX . "choices WHERE question_id = $id ORDER BY id ASC");
		if(count($choices) > 0){
			foreach($choices as $c){
				$layout->AddContentById('choices', '<input type="text" class="form-control" name="choice[id_'.$c->id.']" value="'.$c->choice.'" placeholder="{{ST:choice}}">&nbsp;<a onclick="return confirm(\'{{ST:are_you_sure}}\');" href="'. BASE_URL . 'question.php?id='.$id.'&delete_choice='.$c->id.'"><i class="icon-trash"></i></a><br/><br/>{{ID:choices}}');
			}
		}
	}else{
		$layout->AddContentById('hide_multi', 'style="display:none;"');
		$layout->AddContentById('choices', '<input type="text" class="form-control" name="choice[]" value="" placeholder="{{ST:choice}}"><br/><br/>');
	}
	
	if($question->is_required == 'y'){
		$layout->AddContentById('is_required_state', 'checked="checked"');
	}
	
	$files = unserialize($question->attachment);
	if(count($files) > 0 AND is_array($files)){
		$files_lists = '<ol>';
		$files_count = 1;
		foreach($files as $f){
			if(is_image_file($f)){
				$files_lists .= '<li><a target="_blank" href="'. BASE_URL . 'uploads/' . $f.'" rel="prettyPhoto[gal]">{{ST:attachment}} '.$files_count.'</a> - <a onclick="return confirm(\'{{ST:are_you_sure}}\');" href="question.php?id='.$id.'&delete_file='.$files_count.'">Delete</a></li>';
			}else{
				$files_lists .= '<li><a target="_blank" href="'. BASE_URL . 'uploads/' . $f.'">{{ST:attachment}} '.$files_count.'</a> - <a onclick="return confirm(\'{{ST:are_you_sure}}\');" href="question.php?id='.$id.'&delete_file='.$files_count.'">Delete</a></li>';
			}
			$files_count++;
		}
		$files_lists .= '</ol>';
		$layout->AddContentById('files', $files_lists);
	}
}

$layout->RenderViewAndExit();
