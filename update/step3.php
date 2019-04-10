<?php

session_start();
error_reporting(E_ALL ^ E_NOTICE);

require_once '../php/config.php';
require_once '../php/db.php';

?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Survey Engine Update</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="">

    <!-- Le styles -->
    <link href="../assets/css/bootstrap.css" rel="stylesheet">
    <style type="text/css">
      body {
        padding-top: 20px;
        padding-bottom: 40px;
      }

      /* Custom container */
      .container-narrow {
        margin: 0 auto;
        max-width: 700px;
      }
      .container-narrow > hr {
        margin: 30px 0;
      }

      /* Main marketing message and sign up button */
      .jumbotron {
        margin: 60px 0;
        text-align: center;
      }
      .jumbotron h1 {
        font-size: 72px;
        line-height: 1;
      }
      .jumbotron .btn {
        font-size: 21px;
        padding: 14px 24px;
      }

      /* Supporting marketing content */
      .marketing {
        margin: 60px 0;
      }
      .marketing p + h4 {
        margin-top: 28px;
      }
    </style>
    <link href="../css/bootstrap.css" rel="stylesheet">

    <!-- HTML5 shim, for IE6-8 support of HTML5 elements -->
    <!--[if lt IE 9]>
      <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]-->

    <!-- Fav and touch icons -->
  </head>

  <body>

    <div class="container-narrow">

      <div class="masthead">
        <h3 class="muted">Survey Engine Update</h3>
      </div>

      <hr>
      
<?php

$db = new Db;

$output = array();
$output['status'] = 1;

$surveys = $db->get_results("SELECT * FROM " . TABLES_PREFIX . "survey ORDER BY id DESC");

if($surveys){
	foreach($surveys as $s){
		$questions = $db->get_results("SELECT * FROM " . TABLES_PREFIX . "question WHERE survey_id = ".$s->id." ORDER BY id ASC");
		if($questions){
			foreach($questions as $question){
				if($question->question_type == 'nc' OR $question->question_type == 'nd' OR $question->question_type == 'tf' OR  
					$question->question_type == 'mp' OR $question->question_type == 'ma'){
					$q_stats = array();
					if($question->question_type == 'tf'){
						$trues = $db->get_var("SELECT COUNT(*) FROM " . TABLES_PREFIX . "answers WHERE question_id = ".$question->id." AND answer = 't'");
						$falses = $db->get_var("SELECT COUNT(*) FROM " . TABLES_PREFIX . "answers WHERE question_id = ".$question->id." AND answer = 'f'");
						$q_stats = array('t'=>$trues,'f'=>$falses);
						$db->update(TABLES_PREFIX . "question", array('stats'=>serialize($q_stats)), array('id'=>$question->id), array("%s"));
					}elseif($question->question_type == 'nc' OR $question->question_type == 'nd'){
						$sum = $db->get_var("SELECT SUM(answer) FROM " . TABLES_PREFIX . "answers WHERE question_id = ".$question->id);
						$max = $db->get_var("SELECT MAX(answer) FROM " . TABLES_PREFIX . "answers WHERE question_id = ".$question->id);
						$min = $db->get_var("SELECT MIN(answer) FROM " . TABLES_PREFIX . "answers WHERE question_id = ".$question->id);
						$n = $db->get_var("SELECT COUNT(*) FROM " . TABLES_PREFIX . "answers WHERE question_id = ".$question->id);
						$q_stats = array('total'=>$sum,'n'=>$n,'max'=>floatval($max),'min'=>9999999999);
						if($n > 0){
							$q_stats['min'] = floatval($min);
						}else{
							$q_stats['min'] = 9999999999;
						}
						$db->update(TABLES_PREFIX . "question", array('stats'=>serialize($q_stats)), array('id'=>$question->id), array("%s"));
					}else{
						$choices = $db->get_results("SELECT * FROM " . TABLES_PREFIX . "choices WHERE question_id = ".$question->id." ORDER BY id ASC");
						if(count($choices) > 0){
							foreach($choices as $c){
								$q_stats[$c->id] = $db->get_var("SELECT COUNT(*) FROM " . TABLES_PREFIX . "answers WHERE question_id = ".$question->id." AND choice_id = " . $c->id);
							}
						}
						
						if($question->question_type == 'ma'){
							$q_stats['_total_selects'] =  $db->get_var("SELECT COUNT(DISTINCT results_id) FROM " . TABLES_PREFIX . "answers WHERE question_id = ".$question->id);
						}
						
						$db->update(TABLES_PREFIX . "question", array('stats'=>serialize($q_stats)), array('id'=>$question->id), array("%s"));	
					}
				}
			}
		}
	}
	?>
	<div class="jumbotron">
        <h2>Step 3: Update Statistics - <span class="label label-success">Complete</span></h2>
        <br/>
        <p class="lead">The update process is complete...</p>
      </div>
	<?php
}else{
	?>
	<div class="jumbotron">
        <h2>Step 3: Update Statistics - <span class="label label-important">Error!</span></h2>
        <br/>
         <p class="lead">There are no surveys to update...</p>
      </div>
	<?php
}

?>      

      

      <hr>

      <div class="footer">
        <p>&copy; Survey Engine 2013</p>
      </div>

    </div> <!-- /container -->

    <!-- Le javascript
    ================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->
    <script src="../js/jquery.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>

  </body>
</html>

