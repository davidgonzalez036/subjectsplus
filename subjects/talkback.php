<?php
/**
 *   @file talback.php
 *   @brief Suggestion box public interface.  based on file in subjectsplus, talkback.php
 *   @description When someone submits a comment, it will be scrubbed and added to
 *   the talkback table in the db.  If you want to receive an email when something
 *   comes in, leave $send_email_notification set to "1".  By default, this email
 *   goes to the person set as the admin in config.php; if you want to change this
 *   to someone else, do so below
 *   This email function uses the PHP mail() function; if this doesn't work in
 *   your environment, turn $send_email_notification off
 *   In version 2.x of SP, there are now filters to add different talkback instances
 *
 *   @author adarby
 *   @date update july 2012
 *   @todo
 */

include("../control/includes/config.php");
include("../control/includes/functions.php");

try {
	$dbc = new sp_DBConnector($uname, $pword, $dbName_SPlus, $hname);
} catch (Exception $e) {
	echo $e;
}

/* Set local variables */

$page_title = _("Talk Back");
$page_description = _("Share your comments and suggestions about the library");
$page_keywords = _("library, comments, suggestions, complaints");

// Skill testing question + answer
$stk = _("5 times 5 = ");
$stk_answer = "25";

// Show headshots
$show_talkback_face = 1;

$form_action = "talkback.php"; // this can be overriden below
$bonus_sql = ""; // ditto
$set_filter = ""; // tritto

/////////////////////////
// Deal with multiple talkback instances
// Usually if you have branch libraries who want separate
// pages/results
////////////////////////

if (isset($all_tbtags)) {
// Let's get the first item off the tb array to use as our default
  reset($all_tbtags); // make sure array pointer is at first element
  $set_filter = key($all_tbtags); 

// And set our default bonus sql
  $bonus_sql = "AND tbtags LIKE '%" . $set_filter . "%'";

// determine branch/filter
  if (isset($_REQUEST["v"])) {
  	$set_filter = scrubData(lcfirst($_REQUEST["v"]));
  	$bonus_sql = "AND tbtags LIKE '%" . $set_filter . "%'";

    // Quick'n'dirty setup email recipients
  	switch ($set_filter) {
  		case "music":
  		$page_title = "Comments for the Music Library";
  		$form_action = "talkback.php?v=$set_filter";
  		$tb_bonus_css = "talkback_form_music";
  		break;
  		case "rsmas":
  		$page_title = "Comments for the Marine Library";
  		$form_action = "talkback.php?v=$set_filter";
  		break;
  		default:
        // nothing, we just use the $administrator email on file (config.php)
  		$form_action = "talkback.php";
  	}

    // override our admin email
  	if (isset($all_tbtags[$set_filter]) && $all_tbtags[$set_filter] != "") {
  		$administrator_email = $all_tbtags[$set_filter];
  	}

  } else {

  }
}

///////////////////////
// Feedback
///////////////////////

$feedback = "";

$submission_feedback = "
<div class=\"pluslet\">\n
<div class=\"titlebar\"><div class=\"titlebar_text\" style=\"\">" . _("Thanks") . "</div></div>\n
<div class=\"pluslet_body\">\n
<p>" . _("Thank you for your feedback.  We will try to post a response within the next three business days.") . "</p>\n
</div>\n
</div>\n
";

$submission_failure_feedback = "
<div class=\"pluslet\">\n
<div class=\"titlebar\"><div class=\"titlebar_text\" style=\"\">" . _("Oh dear.") . "</div></div>\n
<div class=\"pluslet_body\">\n
<p>" . _("There was a problem with your submission.  Please try again.") . "</p>
<p>" . _("If you continue to get an error, please contact the <a href=\"mailto:$administrator_email\">administrator</a>") . "
</div>\n
</div>\n";

//////////////////////
// Some email stuff
//////////////////////

$send_email_notification = 1;
$send_to = $administrator_email;
/* Use any ol' email address as from, to make sure the mail works */
$sent_from = $administrator_email;

// clean up post variables
if (isset($_POST["name"])) {
	$this_name = scrubData($_POST["name"]);
} else {
	$this_name = "";
}

if (isset($_POST["the_suggestion"])) {
	$this_comment = scrubData($_POST["the_suggestion"]);
} else {
	$this_comment = "";
}

//////////////////////
// date and time stuff
//////////////////////

$today = getdate();
$month = $today['month'];
$mday = $today['mday'];
$year = $today['year'];
$this_year = date("Y");

$todaycomputer = date('Y-m-d H:i:s');

if (isset($_POST['the_suggestion']) && ($_POST['skill'] == $stk_answer)) {

// clean submission and enter into db!  Don't show page again.

	if ($this_name == "") {
		$this_name = "Anonymous";
	}

  // Make a safe query
	$query = sprintf("INSERT INTO talkback (`question`, `q_from`, `date_submitted`, `display`, `answer`, `tbtags`) VALUES ('%s', '%s', '%s', 'No', '', '%s')", mysql_real_escape_string($this_comment), mysql_real_escape_string($this_name), $todaycomputer, mysql_real_escape_string($set_filter));
  //print $query;
	mysql_query($query);

	if ($query) {
		$stage_one = "ok";
	}

	if (isset($debugger) && $debugger == "yes") {
		print "<p class=\"debugger\">$query<br /><strong>from</strong> this file</p>";
	}

  // Send an email if this is turned on
	if ($send_email_notification == 1) {
		ini_set("SMTP", $email_server);
		ini_set("sendmail_from", $sent_from);

		/* here the subject and header are assembled */

		$subject = "Talk Back";
		$header = "Return-Path: $sent_from\n";
		$header .= "From:  $sent_from\n";
		$header .= "Content-Type: text/html; charset=iso-8859-1;\n\n";

		$message = "<html><body><h2>Talk Back!</h2>\n\n";
		$message .= "<strong>Date Submitted</strong>: $month $mday, $year<br />\n\n";
		$message .= "<strong>Name</strong>:  ";
		$message .= mysql_real_escape_string($this_name);
		$message .= "<br />\n\n
		<strong>Question</strong>:  ";
		$message .= mysql_real_escape_string($this_comment);
		$message .= "<br /><br />\n\n";
		$message .= "</body></html>";

    // begin assembling actual message

		$success = mail($send_to, "$subject", $message, $header);
    // The below is just for testing purposes
		if ($success) {
			$stage_two = "ok";
      //print "mail sent to $send_to";
		} else {
			$stage_two = "fail";
      //print "mail didn't go to $send_to";
		}
	}

	if ($stage_one == "ok" && $stage_two == "ok") {
		$feedback = $submission_feedback;
		$this_name = "";
		$this_comment = "";
	} else {
		$feedback = $submission_failure_feedback;
	}
}

////////////////////
// Display the page
////////////////////

if (isset($_GET["t"]) && $_GET["t"] == "prev") {
	$q_archived = "  SELECT talkback_id, question, q_from, date_submitted, DATE_FORMAT(date_submitted, '%b %d %Y') as thedate, 
	answer, a_from, fname, lname, email, staff.title, YEAR(date_submitted) as theyear
	FROM talkback LEFT JOIN staff 
	ON talkback.a_from = staff.staff_id 
	WHERE (display ='1' OR display ='Yes') 
	$bonus_sql
	AND YEAR(date_submitted) < '$this_year' 
	GROUP BY theyear, date_submitted ORDER BY date_submitted DESC ";

	$our_result = MYSQL_QUERY($q_archived);

	$comment_header = "<h2>" . _("Comments from Previous Years") . " <span style=\"font-size: 12px;\"><a href=\"talkback.php?v=$set_filter\">" . _("See this year") . "</a></span></h2>";

} else {
  // New ones //
	$full_query = "SELECT talkback_id, question, q_from, date_submitted, DATE_FORMAT(date_submitted, '%b %d %Y') as thedate, answer, a_from, fname, lname, email, staff.title 
	FROM talkback LEFT JOIN staff 
	ON talkback.a_from = staff.staff_id 
	WHERE (display ='1' OR display ='Yes') 
	$bonus_sql
	AND YEAR(date_submitted) >= '$this_year' 
	ORDER BY date_submitted DESC";

	$our_result = MYSQL_QUERY($full_query);

	$comment_header = "<h2>" . _("Comments from ") . "$this_year <span style=\"font-size: 11px; font-weight: normal;\"><a href=\"talkback.php?t=prev&v=$set_filter\">" . _("See previous years") . "</a></span></h2>";

}


/* Select all Records, either current or previous year*/

$result_count = mysql_num_rows($our_result);

if ($result_count != 0) {

	$row_count = 1;
	$results = "";

	while ($myrow = mysql_fetch_array($our_result)) {

		$talkback_id = $myrow["0"];
		$question = $myrow["1"];
		$answer = $myrow["5"];
		$answer = preg_replace('/<\/?div.*?>/ ', '', $answer);
    // $answer = stripslashes(htmlspecialchars_decode($myrow["5"])); Louisa's proposed fix for messy answer @todo
		$keywords = $myrow["3"];

		$results .= "
		<div class=\"tellus_item oddrow\">\n
		<a name=\"$talkback_id\"></a>\n
		<p class=\"tellus_comment\"><span class=\"comment_num\">$row_count</span> <strong>$question</strong><br />
		<span style=\"clear: both;font-size: 11px;\">Comment from $myrow[2] on <em>$myrow[4]</em></span></p><br />\n
		<p>";
		if ($show_talkback_face == 1) {
			$results .= getHeadshot($myrow[9]);
		}
		$results .= $answer;
		$results .= "<p style=\"clear: both;font-size: 11px;\">Answered by $myrow[7] $myrow[8], $myrow[10]</p></div>\n";

    // Add 1 to the row count, for the "even/odd" row striping

		$row_count++;
	}
} else {
	$results = "<p>" . _("There are no comments just yet.  Be the first!") . "</p>";
	$no_results = TRUE;
}


///////////////////
// Incomplete Comment submission
///////////////////

if (isset($_POST['skill']) and $_POST['skill'] != $stk_answer) {

	$stk_message = "
	<div class=\"pluslet\">\n
	<div class=\"titlebar\"><div class=\"titlebar_text\" style=\"\">" ._("Hmm, That Was a Tricky Bit of Math") . "</div></div>\n
	<div class=\"pluslet_body\">\n
	<p><strong>" . _("Sorry, you must answer the Skill Testing Question correctly.  It's an anti-spam measure . . . .") . "</strong></p>
	</ul>\n
	</div>\n
	</div>\n
	";

} else {
	$stk_message = "";
}


include("includes/header.php");

//////////////////////
// To Respond or Not
// Setup our columns
if ($is_responsive == TRUE) {
	$ldiv = "class=\"span8\"";
	$rdiv = "class=\"span4\"";
} else {
	$ldiv = "id=\"leftcol\" style=\"width: 65%;\"";
	$rdiv = "id=\"rightcol\" style=\"width: 32%;\"";
}


?>
<div <?php print $ldiv; ?>>
	<?php print $feedback . $stk_message; ?>
	<div class="pluslet_simple no_overflow">

		<?php print _("<p><strong>Talk Back</strong> is where you can <strong>ask a question</strong> or <strong>make a suggestion</strong> about library services.</p>
			

		<p>So, please let us know what you think, and we will post your suggestion and an answer from one of our helpful staff members</p>"); ?>
	</div>
	<div class="pluslet_simple no_overflow">
		<?php print $comment_header . $results; ?>

	</div>  
</div>
<div <?php print $rdiv; ?>>
	<!-- start pluslet -->
	<div class="pluslet">
		<div class="titlebar"><div class="titlebar_text"><?php print _("Tell Us What You Think"); ?></div></div>
		<div class="pluslet_body">
			<p><span class="comment_num">!</span><strong><?php print _("Wait!  Do you need help right now?"); ?></strong><br /><?php print _("Visit the Research Desk!"); ?></p>
			<br />
			<form id="tellus" action="talkback.php" method="post">
				<p class="zebra oddrow"><strong><?php print _("your name (optional):"); ?></strong><br />
					<input type="text" name="name" size="20" value="<?php print $this_name; ?>" /></p>

					<p class="zebra evenrow"><strong><?php print _("comment:"); ?></strong><br />
						<textarea name="the_suggestion" cols="25" rows="4"><?php print $this_comment; ?></textarea></p>

						<p class="zebra oddrow"><strong><?php print $stk; ?></strong><br />
							<?php print _("Enter Number:"); ?> <input type="text" name="skill" size="2" /></p>

							<p class="zebra evenrow"><input type="submit" name="submit_comment" value="<?php print _("Submit"); ?>" /></p>
						</form>
					</div>
				</div>
				<!-- end pluslet -->
				<br />

			</div>
			<!-- END BODY CONTENT -->
			<?php

///////////////////////////
// Load footer file
///////////////////////////

			include("includes/footer.php");

			?>