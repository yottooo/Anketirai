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



$layout = PrivatePage('questions',  $db->get_var("SELECT title FROM " . TABLES_PREFIX . "survey WHERE id = $id") . ' - {{ST:manage_questions}}');

if(isset($_GET['message']) AND $_GET['message'] != ''){
	
	if($_GET['message'] == 'deleted'){
		$layout->AddContentById('alert', $layout->GetContent('alert'));
		$layout->AddContentById('alert_nature', ' alert-success');
		$layout->AddContentById('alert_heading', '{{ST:success}}!');
		$layout->AddContentById('alert_message', '{{ST:the_item_has_been_deleted}}');
	}
}

$layout->AddContentById('id', $id);

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
		
		
		if($_POST['question_type'] == 'mp' OR $_POST['question_type'] == 'ma' OR $_POST['question_type'] == 'dd'){
			if(isset($_POST['choice']) AND count($_POST['choice']) > 0){
				foreach($_POST['choice'] as $choice){
					if($choice != ''){
						$choices[] = $choice;
						$layout->AddContentById('choices', '<input type="text" class="form-control" name="choice[]" value="'.$choice.'" placeholder="{{ST:choice}}"><br/>{{ID:choices}}');
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
			$layout->AddContentById('choices', '<input type="text" class="form-control" name="choice[]" value="" placeholder="{{ST:choice}}"><br/>');
		}
	}else{
		$errors = true;
		$error_msg .= '{{ST:question_type_required}} ';
		$layout->AddContentById('hide_multi', 'style="display:none;"');
		$layout->AddContentById('choices', '<input type="text" class="form-control" name="choice[]" value="" placeholder="{{ST:choice}}"><br/>');
	}
	
	$files = array();
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
		
		
		$values['attachment'] = serialize($files);
		$format[] = "%s";
		
		
	}else{
		$values['attachment'] = "";
		$format[] = "%s";
	}
	
	if(isset($_POST['is_required'])){
		$layout->AddContentById('is_required_state', 'checked="checked"');
		$values['is_required'] = 'y';
		$format[] = "%s";
	}else{
		$values['is_required'] = 'n';
		$format[] = "%s";
	}
	
	if(!$errors){
		$values['survey_id'] = $id;
		$format[] = "%d";
		$new_survey = $db->insert(TABLES_PREFIX . "question", $values, $format);
		if($new_survey){
			$new = $db->insert_id;
			if(count($files_temp) > 0 AND is_array($files_temp)){
				$files_lists = '<ol>';
				$files_count = 1;
				foreach($files_temp as $temp){
					move_uploaded_file($temp["temp"], 'uploads/' . $temp["name"]);
					if(is_image_file($temp["name"])){
						$files_lists .= '<li><a target="_blank" href="'. BASE_URL . 'uploads/' . $temp["name"].'" rel="prettyPhoto[gal]">{{ST:attachment}} '.$files_count.'</a></li>';
					}else{
						$files_lists .= '<li><a target="_blank" href="'. BASE_URL . 'uploads/' . $temp["name"].'">{{ST:attachment}} '.$files_count.'</a></li>';
					}
					$files_count++;
				}
				$files_lists .= '</ol>';
				$layout->AddContentById('files', $files_lists);
			}
			
			if(count($choices) > 0){
				foreach($choices as $c){
					$db->insert(TABLES_PREFIX . "choices", array('survey_id'=>$id,'question_id'=>$new,'choice'=>$c), array("%d","%d","%s"));
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
			$layout->AddContentById('alert_message', '{{ST:unknow_error_try_again}}');
		}
	}else{
		$layout->AddContentById('alert', $layout->GetContent('alert'));
		$layout->AddContentById('alert_nature', ' alert-danger');
		$layout->AddContentById('alert_heading', '{{ST:error}}!');
		$layout->AddContentById('alert_message', $error_msg);
	}
}else{
	$layout->AddContentById('hide_multi', 'style="display:none;"');
	$layout->AddContentById('choices', '<input type="text" class="form-control" name="choice[]" value="" placeholder="{{ST:choice}}"><br/>');
}


$questions = $db->get_results("SELECT * FROM " . TABLES_PREFIX . "question WHERE survey_id = $id ORDER BY order_by ASC, id ASC");
$questions_rows_html = '';
if($questions){
	foreach($questions as $question){
		$row_layout = new Layout('html/','str/');
		$row_layout->SetContentView('survey-edit-questions-rows');
		$row_layout->AddContentById('id', $question->id);
		$row_layout->AddContentById('question', TrimText($question->question, 50));
		
		if(intval($question->order_by) != 9999){
			$row_layout->AddContentById('order_by', intval($question->order_by));
		}
		
		if($question->question_type == 'tf'){
			$row_layout->AddContentById('type', '{{ST:true_or_false}}');
		}elseif($question->question_type == 'mp'){
			$row_layout->AddContentById('type', '{{ST:multiple_choice_single_answer}}');
		}elseif($question->question_type == 'ma'){
			$row_layout->AddContentById('type', '{{ST:multiple_choice_multiple_answers}}');
		}elseif($question->question_type == 'st'){
			$row_layout->AddContentById('type', '{{ST:short_text}}');
		}elseif($question->question_type == 'lt'){
			$row_layout->AddContentById('type', '{{ST:long_text}}');
		}elseif($question->question_type == 'nd'){
			$row_layout->AddContentById('type', '{{ST:numeric_discrete}}');
		}elseif($question->question_type == 'nc'){
			$row_layout->AddContentById('type', '{{ST:numeric_continuous}}');
        }elseif($question->question_type == 'em'){
            $row_layout->AddContentById('type', '{{ST:email}}');
        }elseif($question->question_type == 'dt'){
            $row_layout->AddContentById('type', '{{ST:date}}');
        }elseif($question->question_type == 'dd'){
            $row_layout->AddContentById('type', '{{ST:drop_down}}');
        }
		
		$questions_rows_html .= $row_layout-> ReturnView();
	}
	
}else{
	$questions_rows_html = '<tr><td colspan="3">{{ST:no_items}}</td></tr>';
}

$layout->AddContentById('rows', $questions_rows_html);



$layout->RenderViewAndExit();
