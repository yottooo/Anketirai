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
$layout = PrivatePage('survey-edit-main', '{{ST:manage_survey}}');

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
} else {
    $id = null;
}
$survey = null;

if (isset($_POST['delete'])) {
    $db->query("DELETE FROM " . TABLES_PREFIX . "survey WHERE id = " . $id);
    $db->query("DELETE FROM " . TABLES_PREFIX . "results WHERE survey_id = " . $id);
    $db->query("DELETE FROM " . TABLES_PREFIX . "question WHERE survey_id = " . $id);
    $db->query("DELETE FROM " . TABLES_PREFIX . "choices WHERE survey_id = " . $id);
    $db->query("DELETE FROM " . TABLES_PREFIX . "answers WHERE survey_id = " . $id);
    Leave('index.php?message=deleted');
}

if (isset($_GET['message']) AND $_GET['message'] != '') {
    if ($_GET['message'] == 'new') {
        $layout->AddContentById('alert', $layout->GetContent('alert'));
        $layout->AddContentById('alert_nature', ' alert-success');
        $layout->AddContentById('alert_heading', '{{ST:success}}!');
        $layout->AddContentById('alert_message', '{{ST:the_item_has_been_saved}}');
    }
}

if (isset($_GET['delete_result'])) {
    $db->query("DELETE FROM " . TABLES_PREFIX . "results WHERE id = " . intval($_GET['delete_result']));
    $db->query("DELETE FROM " . TABLES_PREFIX . "answers WHERE results_id = " . intval($_GET['delete_result']));
    $layout->AddContentById('alert', $layout->GetContent('alert'));
    $layout->AddContentById('alert_nature', ' alert-success');
    $layout->AddContentById('alert_heading', '{{ST:success}}!');
    $layout->AddContentById('alert_message', '{{ST:the_item_has_been_deleted}}');
}


if (isset($_GET['active_tab']) AND $_GET['active_tab'] != '') {
    switch ($_GET['active_tab']) {
        case 'questions':
            $layout->AddContentById('tab_questions_active', ' class="active"');
            $layout->AddContentById('pane_questions_active', ' active');
            break;
        case 'results':
            $layout->AddContentById('tab_results_active', ' class="active"');
            $layout->AddContentById('pane_results_active', ' active');
            break;
        case 'statistics':
            $layout->AddContentById('tab_statistics_active', ' class="active"');
            $layout->AddContentById('pane_statistics_active', ' active');
            break;
        case 'two_way_tables':
            $layout->AddContentById('tab_two_way_tables_active', ' class="active"');
            $layout->AddContentById('pane_two_way_tables_active', ' active');
            break;
        default:
            $layout->AddContentById('tab_questions_active', ' class="active"');
            $layout->AddContentById('pane_questions_active', ' active');
            break;
    }
} else {
    $layout->AddContentById('tab_questions_active', ' class="active"');
    $layout->AddContentById('pane_questions_active', ' active');
}

if ($id == null) {
    $layout->AddContentById('preview_state', 'style="display:none;"');
    $layout->AddContentById('delete_state', 'style="display:none;"');

    $layout->AddContentById('questions', '<p>{{ST:you_first_need_to_save_the_survey}}</p>');
    $layout->AddContentById('results', '<p>{{ST:you_first_need_to_save_the_survey}}</p>');
    $layout->AddContentById('statistics', '<p>{{ST:you_first_need_to_save_the_survey}}</p>');
    $layout->AddContentById('two_way_tables', '<p>{{ST:you_first_need_to_save_the_survey}}</p>');
} else {
    if (count($db->get_results("SELECT * FROM " . TABLES_PREFIX . "question WHERE survey_id = $id")) == 0) {
        $layout->AddContentById('preview_state', 'style="display:none;"');
    }
    $layout->AddContentById('id_query', '?id=' . $id);
    $layout->AddContentById('i_query', '?x=' . urlencode(base64_encode(54321 + $id)));
    $layout->AddContentById('base_url', BASE_URL);

    $survey = $db->get_row("SELECT * FROM " . TABLES_PREFIX . "survey WHERE id = $id ORDER BY id DESC LIMIT 0,1");
    if (!$survey) {
        Leave('index.php');
    }

    $layout->AddContentById('questions', $layout->GetContent('survey-edit-questions'));
    $layout->AddContentById('results', $layout->GetContent('survey-edit-results'));
    $layout->AddContentById('statistics', $layout->GetContent('survey-edit-statistics'));
    $layout->AddContentById('two_way_tables', $layout->GetContent('survey-edit-two_way_tables'));

    $layout->AddContentById('id', $id);

    $layout->AddContentById('total_time_taken', count($db->get_results("SELECT * FROM " . TABLES_PREFIX . "results WHERE survey_id = $id ORDER BY id DESC")));
}
//form values
if (isset($_POST['submit'])) {

    $errors = false;
    $values = array();
    $format = array();
    $error_msg = '';
    $meta_values = array();

    if (isset($_POST['title']) AND $_POST['title'] != '') {
        $layout->AddContentById('name', $_POST['title']);
        $values['title'] = Clean($_POST['title']);
        $format[] = "%s";
    } else {
        $errors = true;
        $error_msg .= '{{ST:title_required}} ';
    }

    if (isset($_POST['description']) AND $_POST['description'] != '') {
        $layout->AddContentById('description', $_POST['description']);
        $values['description'] = Clean($_POST['description']);
        $format[] = "%s";
    } else {
        $errors = true;
        $error_msg .= '{{ST:description_required}} ';
    }

    if (isset($_POST['lock'])) {
        $layout->AddContentById('lock_state', 'checked="checked"');
        $values['status'] = 'inactive';
        $format[] = "%s";
    } else {
        $values['status'] = 'published';
        $format[] = "%s";
    }

    if (isset($_POST['email'])) {
        $layout->AddContentById('email_state', 'checked="checked"');
        $values['email'] = 'y';
        $format[] = "%s";
    } else {
        $values['email'] = 'n';
        $format[] = "%s";
    }

    if (isset($_POST['redirect_url']) AND $_POST['redirect_url'] != '') {
        $layout->AddContentById('redirect_url', $_POST['redirect_url']);
        $values['redirect_url'] = Clean($_POST['redirect_url']);
        $format[] = "%s";
    } else {
        $values['redirect_url'] = "";
        $format[] = "%s";
    }

    if (isset($_POST['daily_limit']) AND $_POST['daily_limit'] != '') {
        $layout->AddContentById('daily_limit', $_POST['daily_limit']);
        $values['daily_limit'] = intval($_POST['daily_limit']);
        $format[] = "%d";
    } else {
        $values['daily_limit'] = 0;
        $format[] = "%d";
    }

    if (isset($_POST['total_limit']) AND $_POST['total_limit'] != '') {
        $layout->AddContentById('total_limit', $_POST['total_limit']);
        $values['total_limit'] = intval($_POST['total_limit']);
        $format[] = "%d";
    } else {
        $values['total_limit'] = 0;
        $format[] = "%d";
    }

    if ($id == null) {
        $layout->AddContentById('hide_lock', 'style="display:none;"');
    }
    $values['fbname'] = $profile['name'];
    $format[] = "%s";
    
        $values['days'] = $_POST['days'];
    $format[] = "%d";
    if (!$errors) {
        $has_been_saved = false;
        if ($id == null) {
        
            $values['date_created'] = date('Y-m-d H:i:s');
            $format[] = "%s";
            if ($db->insert(TABLES_PREFIX . "survey", $values, $format)) {
                $has_been_saved = true;
            }
        } else {
            $db->update(TABLES_PREFIX . "survey", $values, array('id' => $id), $format);
            $has_been_saved = true;
        }

        if ($id == null) {
            $survey_id = $db->insert_id;
        } else {
            $survey_id = $id;
        }

        if ($has_been_saved) {
            if ($id == null) {
                Leave('surveys.php?id=' . $survey_id . '&message=new');
            } else {
                $layout->AddContentById('alert', $layout->GetContent('alert'));
                $layout->AddContentById('alert_nature', ' alert-success');
                $layout->AddContentById('alert_heading', '{{ST:success}}!');
                $layout->AddContentById('alert_message', '{{ST:the_item_has_been_saved}}');
            }
        } else {
            $layout->AddContentById('alert', $layout->GetContent('alert'));
            $layout->AddContentById('alert_nature', ' alert-danger');
            $layout->AddContentById('alert_heading', '{{ST:error}}!');
            $layout->AddContentById('alert_message', '{{ST:unknow_error_try_again}}');
        }
    } else {
        $layout->AddContentById('alert', $layout->GetContent('alert'));
        $layout->AddContentById('alert_nature', ' alert-danger');
        $layout->AddContentById('alert_heading', '{{ST:error}}!');
        $layout->AddContentById('alert_message', $error_msg);
    }
} else {
    if ($id != null) {
        $layout->AddContentById('name', $survey->title);
        $layout->AddContentById('description', $survey->description);
        $layout->AddContentById('redirect_url', $survey->redirect_url);

        if (intval($survey->daily_limit) > 0) {
            $layout->AddContentById('daily_limit', $survey->daily_limit);
        }

        if (intval($survey->total_limit) > 0) {
            $layout->AddContentById('total_limit', $survey->total_limit);
        }

        if ($survey->status == 'inactive') {
            $layout->AddContentById('lock_state', 'checked="checked"');
        }

        if ($survey->email == 'y') {
            $layout->AddContentById('email_state', 'checked="checked"');
        }
    }
}

if ($id != null) {

    if (defined('SEO_FRIENDLY') AND SEO_FRIENDLY == true) {
        $layout->AddContentById('survey_url2', BASE_URL . 't/' . UrlText($survey->title) . '/' . $id . '/');
        $layout->AddContentById('survey_url', urlencode(BASE_URL . 't/' . UrlText($survey->title) . '/' . $id . '/'));
    } else {
        $layout->AddContentById('survey_url2', BASE_URL . 'take_survey.php?x=' . urlencode(base64_encode(54321 + $id)));
        $layout->AddContentById('survey_url', urlencode(BASE_URL . 'take_survey.php?x=' . urlencode(base64_encode(54321 + $id))));
    }

    if (isset($_GET['page'])) {
        $page = intval($_GET['page']);
    } else {
        $page = 1;
    }
    $rows = ROWS_PER_PAGE;

    $offset = ($page - 1) * $rows;
    $scores = $db->get_results("SELECT * FROM " . TABLES_PREFIX . "results WHERE survey_id = $id ORDER BY id DESC LIMIT $offset, $rows");
    $number_of_records = count($db->get_results("SELECT * FROM " . TABLES_PREFIX . "results WHERE survey_id = $id"));
    $number_of_pages = ceil($number_of_records / $rows);

    $scores_rows_html = '';
    if ($scores) {

        foreach ($scores as $score) {

            $row_layout = new Layout('html/', 'str/');
            $row_layout->SetContentView('survey-edit-results-rows');
            $row_layout->AddContentById('id', $score->id);
            $row_layout->AddContentById('survey_id', $id);
            $row_layout->AddContentById('ip', $score->ip_address);
            $row_layout->AddContentById('date', date('j M Y H:i', strtotime($score->date_taken)));

            $scores_rows_html .= $row_layout->ReturnView();
        }

        if ($number_of_records > $rows) {
            $pagination = Paginate('surveys.php?id=' . $id . '&active_tab=results', $page, $number_of_pages, true, 3);
            $layout->AddContentById('results_pagination', $pagination);
        }
    } else {
        $scores_rows_html = '<tr><td colspan="4">{{ST:no_items}}</td></tr>';
    }

    $layout->AddContentById('results_rows', $scores_rows_html);

    $questions = $db->get_results("SELECT * FROM " . TABLES_PREFIX . "question WHERE survey_id = $id ORDER BY order_by ASC, id ASC");
    $questions_rows_html = '';
    if ($questions) {
        foreach ($questions as $question) {
            $row_layout = new Layout('html/', 'str/');
            $row_layout->SetContentView('survey-edit-questions-rows');
            $row_layout->AddContentById('id', $question->id);
            $row_layout->AddContentById('question', TrimText($question->question, 50));

            if (intval($question->order_by) != 9999) {
                $row_layout->AddContentById('order_by', intval($question->order_by));
            }

            if ($question->question_type == 'tf') {
                $row_layout->AddContentById('type', '{{ST:true_or_false}}');
            } elseif ($question->question_type == 'mp') {
                $row_layout->AddContentById('type', '{{ST:multiple_choice_single_answer}}');
            } elseif ($question->question_type == 'ma') {
                $row_layout->AddContentById('type', '{{ST:multiple_choice_multiple_answers}}');
            } elseif ($question->question_type == 'st') {
                $row_layout->AddContentById('type', '{{ST:short_text}}');
            } elseif ($question->question_type == 'lt') {
                $row_layout->AddContentById('type', '{{ST:long_text}}');
            } elseif ($question->question_type == 'nd') {
                $row_layout->AddContentById('type', '{{ST:numeric_discrete}}');
            } elseif ($question->question_type == 'nc') {
                $row_layout->AddContentById('type', '{{ST:numeric_continuous}}');
            } elseif ($question->question_type == 'em') {
                $row_layout->AddContentById('type', '{{ST:email}}');
            } elseif ($question->question_type == 'dt') {
                $row_layout->AddContentById('type', '{{ST:date}}');
            } elseif ($question->question_type == 'dd') {
                $row_layout->AddContentById('type', '{{ST:drop_down}}');
            }

            $questions_rows_html .= $row_layout->ReturnView();
        }
    } else {
        $questions_rows_html = '<tr><td colspan="3">{{ST:no_items}}</td></tr>';
    }

    $layout->AddContentById('questions_rows', $questions_rows_html);

    $arr_FCColors[] = "AFD8F8";
    $arr_FCColors[] = "F6BD0F";
    $arr_FCColors[] = "8BBA00";
    $arr_FCColors[] = "FF8E46";
    $arr_FCColors[] = "008E8E";
    $arr_FCColors[] = "D64646";
    $arr_FCColors[] = "8E468E";
    $arr_FCColors[] = "588526";
    $arr_FCColors[] = "B3AA00";
    $arr_FCColors[] = "008ED6";
    $arr_FCColors[] = "9D080D";
    $arr_FCColors[] = "A186BE";
    $arr_FCColors[] = "CC6600";
    $arr_FCColors[] = "FDC689";
    $arr_FCColors[] = "ABA000";
    $arr_FCColors[] = "F26D7D";
    $arr_FCColors[] = "FFF200";
    $arr_FCColors[] = "0054A6";
    $arr_FCColors[] = "F7941C";
    $arr_FCColors[] = "CC3300";
    $arr_FCColors[] = "006600";
    $arr_FCColors[] = "663300";
    $arr_FCColors[] = "6DCFF6";

    $stats_rows_html = '';
    $stats_rows_counter = 1;
    if ($questions) {
        foreach ($questions as $question) {
            $row_layout = new Layout('html/', 'str/');
            $row_layout->SetContentView('survey-edit-statistics-rows');
            $row_layout->AddContentById('id', $question->id);
            $row_layout->AddContentById('question', $question->question);
            $row_layout->AddContentById('number', $stats_rows_counter);
            $stats_rows_counter++;
            if ($question->question_type == 'st') {
                $row_layout->AddContentById('question_type', '{{ST:short_text}}');
                $row_layout->AddContentById('results', '<p>{{ST:qualitative_data}}</p>');
            } elseif ($question->question_type == 'lt') {
                $row_layout->AddContentById('question_type', '{{ST:long_text}}');
                $row_layout->AddContentById('results', '<p>{{ST:qualitative_data}}</p>');
            } elseif ($question->question_type == 'dt') {
                $row_layout->AddContentById('question_type', '{{ST:date}}');
                $row_layout->AddContentById('results', '<p>{{ST:qualitative_data}}</p>');
            } elseif ($question->question_type == 'em') {
                $row_layout->AddContentById('question_type', '{{ST:email}}');
                $row_layout->AddContentById('results', '<p>{{ST:qualitative_data}}</p>');
            }

            if ($question->question_type == 'nc' OR
                    $question->question_type == 'nd' OR
                    $question->question_type == 'tf' OR
                    $question->question_type == 'mp' OR
                    $question->question_type == 'dd' OR
                    $question->question_type == 'ma') {
                $q_stats = array();
                $stats_total = 0;
                $stats_results_html = '';
                if (!$question->stats) {
                    if ($question->question_type == 'tf') {
                        $q_stats = array('t' => 0, 'f' => 0);
                    } elseif ($question->question_type == 'dd' OR $question->question_type == 'mp' OR $question->question_type == 'ma') {
                        $choices = $db->get_results("SELECT * FROM " . TABLES_PREFIX . "choices WHERE question_id = " . $question->id . " ORDER BY id ASC");
                        if (is_array($choices)) {
                            foreach ($choices as $c) {
                                $q_stats[$c->id] = 0;
                            }
                        }
                        if ($question->question_type == 'ma') {
                            $q_stats['_total_selects'] = 0;
                        }
                    } elseif ($question->question_type == 'nc' OR $question->question_type == 'nd') {
                        $q_stats = array('total' => 0, 'n' => 0, 'max' => 0, 'min' => 9999999999);
                    }
                } else {
                    $q_stats = unserialize($question->stats);
                }

                if ($question->question_type == 'tf') {
                    $stats_total = $db->get_var("SELECT COUNT(Distinct results_id) FROM " . TABLES_PREFIX . "answers WHERE question_id = " . $question->id . " ");
                    $fake_total = 0;
                    $q_stats['t'] = $db->get_var("SELECT COUNT(DISTINCT results_id) FROM " . TABLES_PREFIX . "answers WHERE answer = 't' AND question_id = " . $question->id . " ");
                    $q_stats['f'] = $db->get_var("SELECT COUNT(DISTINCT results_id) FROM " . TABLES_PREFIX . "answers WHERE answer = 'f' AND question_id = " . $question->id . " ");

                    //$stats_total = intval($q_stats['t']) + intval($q_stats['f']);
                    if ($stats_total == 0) {
                        $fake_total = 1;
                    } else {
                        $fake_total = $stats_total;
                    }
                    $true_perc = 0;
                    $false_perc = 0;
                    if ($stats_total > 0) {
                        $true_perc = round((intval($q_stats['t']) / $fake_total) * 100);
                        $false_perc = round((intval($q_stats['f']) / $fake_total) * 100);
                    }
                    $stats_results_html = '<p><b>' . intval($q_stats['t']) . '/' . $stats_total . ' (' . $true_perc . '%)</b> - {{ST:true}}</p><p><b>' . intval($q_stats['f']) . '/' . $stats_total . ' (' . $false_perc . '%)</b> - {{ST:false}}</p>';

                    if (isset($_GET['stats']) AND $_GET['stats'] == 'all') {
                        $chart_layout = new Layout('html/', 'str/');
                        $chart_layout->SetContentView('survey-edit-statistics-rows-charts');
                        $chart_layout->AddContentById('id', $question->id);
                        $chart_layout->AddContentById('type', 'FCF_Pie2D');
                        $chart_layout->AddContentById('width', '300');
                        $chart_layout->AddContentById('height', '300');
                        $chart_data = '<graph showNames="1"  decimalPrecision="0"><set name="{{ST:true}}" value="' . intval($q_stats['t']) . '"/><set name="{{ST:false}}" value="' . intval($q_stats['f']) . '"/></graph>';
                        $chart_layout->AddContentById('data', str_replace("'", "", $chart_data));
                        $row_layout->AddContentById('charts', $chart_layout->ReturnView());
                    }
                } elseif ($question->question_type == 'nc' OR $question->question_type == 'nd') {
                    $question_stats = $db->get_row("SELECT AVG(answer) as Mean, MAX(answer) as Maximum, MIN(answer) as Minimum, SUM(answer) as Total,  VARIANCE(answer) as Var, STDDEV(answer) as StandardDeviation FROM " . TABLES_PREFIX . "answers WHERE question_id = " . $question->id . " ");

                    $stats_results_html = '<p><b>' . $question_stats->Mean . '</b> - {{ST:average}}</p>';
                    $stats_results_html .= '<p><b>' . $question_stats->Maximum . '</b> - {{ST:max}}</p>';
                    $stats_results_html .= '<p><b>' . $question_stats->Minimum . '</b> - {{ST:min}}</p>';
                    $stats_results_html .= '<p><b>' . $question_stats->Total . '</b> - {{ST:total}}</p>';
                    $stats_results_html .= '<p><b>' . $question_stats->Var . '</b> - {{ST:variance}}</p>';
                    $stats_results_html .= '<p><b>' . $question_stats->StandardDeviation . '</b> - {{ST:standard_deviation}}</p>';

                    /*
                      if($q_stats['n'] > 0){
                      $stats_results_html = '<p><b>'.round(($q_stats['total']/$q_stats['n']),2).'</b> - {{ST:average}}</p>';
                      }else{
                      $stats_results_html = '<p><b>0</b> - {{ST:average}}</p>';
                      }
                      $stats_results_html .= '<p><b>'.$q_stats['max'].'</b> - {{ST:max}}</p>';
                      if($q_stats['min'] != 9999999999){
                      $stats_results_html .= '<p><b>'.$q_stats['min'].'</b> - {{ST:min}}</p>';
                      }
                     */
                } elseif ($question->question_type == 'dd' OR $question->question_type == 'mp' OR $question->question_type == 'ma') {
                    $stats_total = $db->get_var("SELECT COUNT(Distinct results_id) FROM " . TABLES_PREFIX . "answers WHERE question_id = " . $question->id . " ");
                    $fake_total = 0;

                    /*
                      if($question->question_type == 'ma'){
                      $stats_total = intval($q_stats['_total_selects']);
                      }else{
                      if(count($q_stats) > 0){
                      foreach($q_stats as $k=>$v){
                      $stats_total = $stats_total + intval($v);
                      }
                      }
                      }
                     */

                    if ($stats_total == 0) {
                        $fake_total = 1;
                    } else {
                        $fake_total = $stats_total;
                    }
                    $choices = $db->get_results("SELECT * FROM " . TABLES_PREFIX . "choices WHERE question_id = " . $question->id . " ORDER BY id ASC");
                    if (is_array($choices)) {
                        foreach ($choices as $c) {
                            $q_stats[$c->id] = $db->get_var("SELECT COUNT(DISTINCT results_id) FROM " . TABLES_PREFIX . "answers WHERE choice_id = " . $c->id . " AND question_id = " . $question->id . " ");
                            $stats_results_html .= '<p><b>' . intval($q_stats[$c->id]) . '/' . $stats_total . ' (' . round((intval($q_stats[$c->id]) / $fake_total) * 100) . '%)</b> - ' . $c->choice . '</p>';
                        }
                    }
                    if (isset($_GET['stats']) AND $_GET['stats'] == 'all') {
                        $chart_layout = new Layout('html/', 'str/');
                        $chart_layout->SetContentView('survey-edit-statistics-rows-charts');
                        $chart_layout->AddContentById('id', $question->id);
                        if ($question->question_type == 'mp' OR $question->question_type == 'dd') {
                            $chart_layout->AddContentById('width', '300');
                            $chart_layout->AddContentById('height', '300');
                            $chart_layout->AddContentById('type', 'FCF_Pie2D');
                        } else {
                            $chart_layout->AddContentById('width', '600');
                            $chart_layout->AddContentById('height', '300');
                            $chart_layout->AddContentById('type', 'FCF_Column2D');
                        }

                        $chart_data = '<graph showNames="1"  decimalPrecision="0">';
                        if (is_array($choices)) {
                            foreach ($choices as $c) {
                                $chart_data .= '<set name="' . $c->choice . '" value="' . intval($q_stats[$c->id]) . '"/>';
                            }
                        }
                        $chart_data .= '</graph>';

                        $chart_layout->AddContentById('data', str_replace("'", "", $chart_data));
                        $row_layout->AddContentById('charts', $chart_layout->ReturnView());
                    }
                }
                $row_layout->AddContentById('results', $stats_results_html);
            }

            $stats_rows_html .= $row_layout->ReturnView();
        }
    } else {
        $stats_rows_html = '<p>{{ST:no_items}}</p>';
    }

    $layout->AddContentById('stats_rows', $stats_rows_html);

    $rows_select_2way = '';
    $column_select_2way = '';
    if ($questions) {
        foreach ($questions as $question) {
            if ($question->question_type == 'dd' OR $question->question_type == 'tf' OR $question->question_type == 'mp' OR $question->question_type == 'ma') {
                $rows_select_2way .= '<option value="' . $question->id . '" {{ID:rows_2way_status_' . $question->id . '}}>' . TrimText($question->question, 50) . '</option>';
                $column_select_2way .= '<option value="' . $question->id . '" {{ID:columns_2way_status_' . $question->id . '}}>' . TrimText($question->question, 50) . '</option>';
            }
        }
    }
    $layout->AddContentById('two_way_rows_select', $rows_select_2way);
    $layout->AddContentById('two_way_columns_select', $column_select_2way);
    if (isset($_POST['submit_2way'])) {
        $error_2way = false;
        $error_msg_2way = '';
        $sample_size_2way = '';
        $rows_2way = null;
        $columns_2way = null;

        if (isset($_POST['sample_size']) AND $_POST['sample_size'] != '') {
            $layout->AddContentById('sample_size', $_POST['sample_size']);
            $sample_size_2way = 'LIMIT ' . intval($_POST['sample_size']);
        }

        if (isset($_POST['rows']) AND $_POST['rows'] != '') {
            $layout->AddContentById('rows_2way_status_' . $_POST['rows'], 'selected');
            $rows_2way = intval($_POST['rows']);
            if (isset($_POST['columns']) AND $_POST['columns'] != '') {
                $layout->AddContentById('columns_2way_status_' . $_POST['columns'], 'selected');
                $columns_2way = intval($_POST['columns']);
                if ($_POST['columns'] == $_POST['rows']) {
                    $error_2way = true;
                    $error_msg_2way .= '{{ST:columns_and_rows_cant_be_the_same}} ';
                }
            } else {
                $error_2way = true;
                $error_msg_2way .= '{{ST:both_rows_columns_required}} ';
            }
        } else {
            $error_2way = true;
            $error_msg_2way .= '{{ST:both_rows_columns_required}} ';
        }

        if (!$error_2way) {
            $layout->AddContentById('two_way_table', $layout->GetContent('survey-edit-two_way_tables-table'));
            $rows_question = $db->get_row("SELECT * FROM " . TABLES_PREFIX . "question WHERE id = $rows_2way ORDER BY order_by ASC, id DESC LIMIT 0,1");
            $columns_question = $db->get_row("SELECT * FROM " . TABLES_PREFIX . "question WHERE id = $columns_2way ORDER BY order_by ASC, id DESC LIMIT 0,1");
            if ($rows_question AND $columns_question) {
                $layout->AddContentById('twoway_rows_name', TrimText($rows_question->question, 50));
                $layout->AddContentById('twoway_columns_name', TrimText($columns_question->question, 50));

                if ($rows_question->question_type == 'tf') {
                    $rows_choices = array(array('id' => 't', 'choice' => 't'), array('id' => 'f', 'choice' => 'f'));
                } else {
                    $rows_choices_q = $db->get_results("SELECT * FROM " . TABLES_PREFIX . "choices WHERE question_id = " . $rows_question->id . " ORDER BY id ASC");
                    $rows_choices = array();
                    foreach ($rows_choices_q as $rcq) {
                        $rows_choices[] = array('id' => $rcq->id, 'choice' => $rcq->choice);
                    }
                }
                if ($columns_question->question_type == 'tf') {
                    $columns_choices = array(array('id' => 't', 'choice' => 't'), array('id' => 'f', 'choice' => 'f'));
                } else {
                    $columns_choices_q = $db->get_results("SELECT * FROM " . TABLES_PREFIX . "choices WHERE question_id = " . $columns_question->id . " ORDER BY id ASC");
                    $columns_choices = array();
                    foreach ($columns_choices_q as $rcq) {
                        $columns_choices[] = array('id' => $rcq->id, 'choice' => $rcq->choice);
                    }
                }

                $layout->AddContentById('twoway_rows_choices_num', count($rows_choices));
                $layout->AddContentById('twoway_columns_choices_num', count($columns_choices));

                if ($columns_question->question_type == 'tf') {
                    $layout->AddContentById('twoway_columns_choices_headers', '<td>{{ST:true}}</td><td>{{ST:false}}</td>');
                    $layout->AddContentById('twoway_columns_total_space', '<td>{{ID:_total_col_t}}</td><td>{{ID:_total_col_f}}</td>');
                } else {
                    $twoway_columns_choices_headers = '';
                    $twoway_columns_total_space = '';
                    foreach ($columns_choices as $c) {
                        $twoway_columns_choices_headers .= '<td>' . $c['choice'] . '</td>';
                        $twoway_columns_total_space .= '<td>{{ID:_total_col_' . $c['id'] . '}}</td>';
                    }
                    $layout->AddContentById('twoway_columns_choices_headers', $twoway_columns_choices_headers);
                    $layout->AddContentById('twoway_columns_total_space', $twoway_columns_total_space);
                }

                $twoway_rows_rows = '';
                if ($rows_question->question_type == 'tf') {
                    $twoway_rows_rows .= '<td>{{ST:true}}</td>';
                    foreach ($columns_choices as $x) {
                        $twoway_rows_rows .= '<td>{{ID:_t_' . $x['id'] . '}}  ({{ID:_t_' . $x['id'] . '_perc_col}}% &darr;)  ({{ID:_t_' . $x['id'] . '_perc_row}}% &rarr;)</td>';
                    }
                    $twoway_rows_rows .= '<td>{{ID:_total_row_t}}</td></tr>';
                    $twoway_rows_rows .= '<tr><td>{{ST:false}}</td>';
                    foreach ($columns_choices as $x) {
                        $twoway_rows_rows .= '<td>{{ID:_f_' . $x['id'] . '}}  ({{ID:_f_' . $x['id'] . '_perc_col}}% &darr;)  ({{ID:_f_' . $x['id'] . '_perc_row}}% &rarr;)</td>';
                    }
                    $twoway_rows_rows .= '<td>{{ID:_total_row_f}}</td></tr>';
                } else {
                    $twoway_rows_row_1 = true;

                    foreach ($rows_choices as $c) {
                        if ($twoway_rows_row_1) {
                            $twoway_rows_rows .= '<td>' . $c['choice'] . '</td>';
                            $twoway_rows_row_1 = false;
                        } else {
                            $twoway_rows_rows .= '<tr><td>' . $c['choice'] . '</td>';
                        }
                        foreach ($columns_choices as $x) {
                            $twoway_rows_rows .= '<td>{{ID:_' . $c['id'] . '_' . $x['id'] . '}} ({{ID:_' . $c['id'] . '_' . $x['id'] . '_perc_col}}% &darr;)  ({{ID:_' . $c['id'] . '_' . $x['id'] . '_perc_row}}% &rarr;)</td>';
                        }
                        $twoway_rows_rows .= '<td>{{ID:_total_row_' . $c['id'] . '}}</td></tr>';
                    }
                }
                $layout->AddContentById('twoway_rows_rows', $twoway_rows_rows);
                $twoway_row_column_values = array();
                $twoway_row_column_values['_grand_total'] = 0;

                foreach ($rows_choices as $c) {
                    foreach ($columns_choices as $x) {
                        $twoway_row_column_values['_total_col_' . $x['id']] = 0;
                        $twoway_row_column_values['_' . $c['id'] . '_' . $x['id']] = 0;
                        $twoway_row_column_values['_' . $c['id'] . '_' . $x['id'] . '_perc_row'] = 0;
                        $twoway_row_column_values['_' . $c['id'] . '_' . $x['id'] . '_perc_col'] = 0;
                    }
                    $twoway_row_column_values['_total_row_' . $c['id']] = 0;
                }

                $twoway_results = $db->get_results("SELECT * FROM " . TABLES_PREFIX . "results WHERE survey_id = $id ORDER BY RAND() $sample_size_2way");
                if ($twoway_results) {
                    foreach ($twoway_results as $r) {
                        $ans_row = $db->get_results("SELECT * FROM " . TABLES_PREFIX . "answers WHERE results_id = " . $r->id . " AND question_id = " . $rows_question->id . " ORDER BY id ASC");
                        $ans_col = $db->get_results("SELECT * FROM " . TABLES_PREFIX . "answers WHERE results_id = " . $r->id . " AND question_id = " . $columns_question->id . " ORDER BY id ASC");

                        $row_ans_id = null;
                        $row_ans_value = null;
                        $col_ans_id = null;
                        $col_ans_value = null;
                        if ($rows_question->question_type == 'tf') {
                            $row_ans_id = $ans_row[0]->answer;
                            $row_ans_value = $ans_row[0]->answer;
                        } elseif ($rows_question->question_type == 'mp' OR $rows_question->question_type == 'dd') {
                            $row_ans_id = $ans_row[0]->choice_id;
                            if ($row_ans_id > 0) {
                                $row_ans_value = $db->get_var("SELECT choice FROM " . TABLES_PREFIX . "choices WHERE id = " . $ans_row[0]->choice_id);
                            }
                        } else {
                            $row_ans_id = array();
                            $row_ans_value = array();
                            foreach ($ans_row as $ar) {
                                $row_ans_id[] = $ar->choice_id;
                                if ($ar->choice_id > 0) {
                                    $row_ans_value[] = $db->get_var("SELECT choice FROM " . TABLES_PREFIX . "choices WHERE id = " . $ar->choice_id);
                                }
                            }
                        }
                        if ($columns_question->question_type == 'tf') {
                            $col_ans_id = $ans_col[0]->answer;
                            $col_ans_value = $ans_col[0]->answer;
                        } elseif ($columns_question->question_type == 'mp' OR $columns_question->question_type == 'dd') {
                            $col_ans_id = $ans_col[0]->choice_id;
                            if ($col_ans_id > 0) {
                                $col_ans_value = $db->get_var("SELECT choice FROM " . TABLES_PREFIX . "choices WHERE id = " . $ans_col[0]->choice_id);
                            }
                        } else {
                            $col_ans_id = array();
                            $col_ans_value = array();
                            foreach ($ans_col as $ac) {
                                $col_ans_id[] = $ac->choice_id;
                                if ($ac->choice_id > 0) {
                                    $col_ans_value = $db->get_var("SELECT choice FROM " . TABLES_PREFIX . "choices WHERE id = " . $ac->choice_id);
                                }
                            }
                        }

                        if ($row_ans_value AND $col_ans_value) {
                            if ($rows_question->question_type != 'ma' AND $columns_question->question_type != 'ma') {
                                $twoway_row_column_values['_total_col_' . $col_ans_id] ++;
                                $twoway_row_column_values['_total_row_' . $row_ans_id] ++;
                                $twoway_row_column_values['_' . $row_ans_id . '_' . $col_ans_id] ++;
                                $twoway_row_column_values['_grand_total'] ++;
                            } elseif ($rows_question->question_type == 'ma' AND $columns_question->question_type != 'ma') {
                                foreach ($row_ans_id as $w) {
                                    $twoway_row_column_values['_total_col_' . $col_ans_id] ++;
                                    $twoway_row_column_values['_total_row_' . $w] ++;
                                    $twoway_row_column_values['_' . $w . '_' . $col_ans_id] ++;
                                    $twoway_row_column_values['_grand_total'] ++;
                                }
                            } elseif ($rows_question->question_type != 'ma' AND $columns_question->question_type == 'ma') {
                                foreach ($col_ans_id as $w) {
                                    $twoway_row_column_values['_total_col_' . $w] ++;
                                    $twoway_row_column_values['_total_row_' . $row_ans_id] ++;
                                    $twoway_row_column_values['_' . $row_ans_id . '_' . $w] ++;
                                    $twoway_row_column_values['_grand_total'] ++;
                                }
                            }
                        }
                    }
                }

                foreach ($rows_choices as $c) {
                    foreach ($columns_choices as $x) {
                        $twoway_row_column_values['_' . $c['id'] . '_' . $x['id'] . '_perc_col'] = Percenter($twoway_row_column_values['_' . $c['id'] . '_' . $x['id']], $twoway_row_column_values['_total_col_' . $x['id']]);
                        $twoway_row_column_values['_' . $c['id'] . '_' . $x['id'] . '_perc_row'] = Percenter($twoway_row_column_values['_' . $c['id'] . '_' . $x['id']], $twoway_row_column_values['_total_row_' . $c['id']]);
                    }
                }

                $layout->AddContentById('_grand_total', $twoway_row_column_values['_grand_total']);
                foreach ($twoway_row_column_values as $k => $v) {
                    $layout->AddContentById($k, $v);
                }
            } else {
                $layout->AddContentById('two_way_table', '<i>{{ST:select_parameters_first}}</i>');
                $layout->AddContentById('alert_2way', $layout->GetContent('alert'));
                $layout->AddContentById('alert_nature', ' alert-danger');
                $layout->AddContentById('alert_heading', '{{ST:error}}!');
                $layout->AddContentById('alert_message', '{{ST:unknow_error_try_again}}');
            }
        } else {
            $layout->AddContentById('two_way_table', '<i>{{ST:select_parameters_first}}</i>');
            $layout->AddContentById('alert_2way', $layout->GetContent('alert'));
            $layout->AddContentById('alert_nature', ' alert-danger');
            $layout->AddContentById('alert_heading', '{{ST:error}}!');
            $layout->AddContentById('alert_message', $error_msg_2way);
        }
    } else {
        $layout->AddContentById('two_way_table', '<i>{{ST:select_parameters_first}}</i>');
        $layout->AddContentById('sample_size', 200);
    }
} else {
    $layout->AddContentById('hide_lock', 'style="display:none;"');
}




$layout->RenderViewAndExit();
