<?php

require 'includes.php';

/*

  if(!AdminCanManageSurvey()){
  if(AdminCanManageAdmins()){
  Leave('admins.php');
  }else{
  Leave('signin.php');
  }
  }
 */
echo '<a class="navbar-brand" href="index.php"> Здравейте ' . ($profile[name]) . ' !</a>';
if (isset($_GET['duplicate']) AND intval($_GET['duplicate']) != 0) {
    DuplicateSurvey(intval($_GET['duplicate']));
    Leave('index.php?message=duplicated');
}

$id = null;
$layout = PrivatePage('home', '{{ST:all_surveys}}');

if (isset($_GET['page'])) {
    $page = intval($_GET['page']);
} else {
    $page = 1;
}
$rows = ROWS_PER_PAGE;

if (isset($_GET['message']) AND $_GET['message'] != '') {

    if ($_GET['message'] == 'deleted') {
        $layout->AddContentById('alert', $layout->GetContent('alert'));
        $layout->AddContentById('alert_nature', ' alert-success');
        $layout->AddContentById('alert_heading', '{{ST:success}}!');
        $layout->AddContentById('alert_message', '{{ST:the_item_has_been_deleted}}');
    }

    if ($_GET['message'] == 'duplicated') {
        $layout->AddContentById('alert', $layout->GetContent('alert'));
        $layout->AddContentById('alert_nature', ' alert-success');
        $layout->AddContentById('alert_heading', '{{ST:success}}!');
        $layout->AddContentById('alert_message', '{{ST:the_survey_has_been_duplicated}}');
    }
}

$offset = ($page - 1) * $rows;
//Sorting
$s = $_GET['s'];
$t = rand(0, 1);
if ($t == 0) {
    $desc = 'DESC';
}
if ($t == 1) {
    $desc = 'ASC';
}
//sort end
if (!IsLoggedIn()) {
    $quizs = $db->get_results("SELECT * FROM " . TABLES_PREFIX . "survey where fbname = '$profile[name]' ORDER BY title DESC LIMIT $offset, $rows");
    if (isset($_GET['s'])) {
        $quizs = $db->get_results("SELECT * FROM " . TABLES_PREFIX . "survey where fbname = '$profile[name]' ORDER BY $s $desc LIMIT $offset, $rows");
    }
} else {
    $quizs = $db->get_results("SELECT * FROM " . TABLES_PREFIX . "survey ORDER BY title DESC LIMIT $offset, $rows");
    if (isset($_GET['s'])) {
        $quizs = $db->get_results("SELECT * FROM " . TABLES_PREFIX . "survey ORDER BY $s $desc LIMIT $offset, $rows");
    }
}

$number_of_records = count($db->get_results("SELECT * FROM " . TABLES_PREFIX . "survey"));


$number_of_pages = ceil($number_of_records / $rows);

$rows_html = '';
if ($quizs) {
    foreach ($quizs as $quiz) {
        $row_layout = new Layout('html/', 'str/');
        $row_layout->SetContentView('home-rows');
        $row_layout->AddContentById('id', $quiz->id);
        $row_layout->AddContentById('name', $quiz->title);
        $row_layout->AddContentById('description', TrimText($quiz->description, 50));
        $row_layout->AddContentById('date_created', $quiz->date_created);

        $rows_html .= $row_layout->ReturnView();
    }

    if ($number_of_records > $rows) {
        if (isset($_GET['id'])) {
            $pagination = Paginate('index.php?id=' . $id, $page, $number_of_pages, true, 3);
        } else {
            $pagination = Paginate('index.php', $page, $number_of_pages, false, 3);
        }
        $layout->AddContentById('pagination', $pagination);
    }
} else {
    $rows_html = '<tr><td colspan="3">{{ST:no_items}}</td></tr>';
}



$layout->AddContentById('rows', $rows_html);



$layout->RenderViewAndExit();
