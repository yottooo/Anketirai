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

      <div class="jumbotron">
        <h1>Important!</h1>
        
        <br/>
        <p class="lead">-> Due to issues with with UTF8 characters in displaying the statistics, this script's database has been redeisgned to fix these problems.</p>
        <p class="lead">-> Please make sure that you have backed up your current database before proceeding.</p>
        
         <p class="lead">-> After backing up, <a target="_blank" href="../install.php">Click Here</a> to install new database tables and then click on the button below.</p>
         
        <a class="btn btn-large btn-success" href="step1.php">Step 1: Update Questions Table</a>
      </div>

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

