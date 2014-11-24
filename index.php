<?php
//////////////////////////////
// Action dispatcher
//////////////////////////////
if ($_SERVER['REQUEST_METHOD'] != 'GET') {
    $actions = array('parse_csv');
    if (isset($_GET['action']) && in_array($_GET['action'], $actions) && function_exists($_GET['action'])) {
        call_user_func($_GET['action']);
        exit();
    } else {
        header('HTTP/1.0 404 Not Found');
        die();
    }
}

//////////////////////////////
// Action handlers
//////////////////////////////
function parse_csv() {
    if (isset($_FILES['csvfile'])) {
        $csv_lines = file($_FILES['csvfile']['tmp_name']);
        $delimiter = (isset($_POST['delimiter']) && $_POST['delimiter']) ? $_POST['delimiter'] : ',';
        if (count($csv_lines) < 2) {
            echo json_encode(array('error' => 'The csv file should at least contain 2 lines (column names + data line)'));
            exit();
        }
        $column_names = str_getcsv($csv_lines[0], $delimiter);
        $data = array();
        for($i=1; $i<count($csv_lines); $i++) $data[] = str_getcsv($csv_lines[$i], $delimiter);
        echo json_encode(array( 'column_names' => $column_names,
                                'data' => $data));
    } else {
        echo json_encode(array('error' => 'Please upload a csv file'));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>csv2mail - Read a csv file, send templated emails</title>

    <!-- jQuery via CDN -->
    <script src="http://code.jquery.com/jquery-latest.js"></script>
    <script src="http://malsup.github.com/jquery.form.js"></script>

    <!-- Bootstrap via CDN -->
    <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css">
    <script src="//netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>
  </head>

    <body>
        <div class="container">
            <a name="step1"></a><h1>Step 1: read csv file</h1>
            <p class="text-muted">Upload a csv file containing the email addresses and dynamic field values to get started.</p>
            <hr>
            <form role="form" id="form_csv" action="index.php?action=parse_csv" method="post" enctype="multipart/form-data"> 
              <div class="form-group">
                <label for="csvInputFile">CSV file</label>
                <input type="file" id="csvInputFile" name="csvfile">
                <p class="help-block">The first line of the csv file should contain the column names.</p>
              </div>
              <div class="form-group">
                Delimiter:
                <label class="radio-inline">
                  <input type="radio" name="delimiter" id="delimiter1" value="," checked> comma (,)
                </label>
                <label class="radio-inline">
                  <input type="radio" name="delimiter" id="delimiter2" value=";"> semicolon (;)
                </label>
                <label class="radio-inline">
                  <input type="radio" name="delimiter" id="delimiter3" value="|"> pipe (|)
                </label>
              </div>

              <button type="submit" class="btn btn-primary">Upload &amp; continue</button>
            </form>

            <div class="step" id="step_preview" style="display:none;">
                <a name="preview"></a><h2>Data preview</h2>
                <table class="table table-striped table-bordered" id="table_csv_preview">
                  <thead></thead>
                  <tbody></tbody>
                </table>
            </div>

            <div class="step" id="step_compose" style="display:none;">
                <a name="compose"></a><h1>Step 2: Compose email</h1>
                <p class="text-muted">
                    Select the roles of the fields. Use {field_name} in the email content.<br/>
                    Available tags: <div id="available_tags"></div>
                </p>
                <hr>
                <form role="form" id="form_compose">
                    <div class="form-group">
                        <label for="email_name">Sender name:</label>
                        <input type="text" class="form-control" id="email_name" name="email_name" placeholder="John Doe">
                    </div>
                    <div class="form-group">
                        <label for="email_address">Sender email:</label>
                        <input type="email" class="form-control" id="email_address" name="email_address" placeholder="john@doe.com">
                    </div>
                    <div class="form-group">
                        <label for="email_recipient_field">Recipient email field:</label>
                        <select class="form-control" id="email_recipient_field" name="email_recipient_field"></select>
                        <p class="help-block">Select the csv column that contains the email addresses of the recipients.</p>
                    </div>
                    <div class="form-group">
                        <label for="email_subject">Subject:</label>
                        <input type="text" class="form-control" id="email_subject" name="email_subject" placeholder="Enter the subject line here">
                    </div>
                    <div class="form-group">
                        <label for="email_contect">Email body:</label>
                        <textarea class="form-control" id="email_body" name="email_body" cols="40" rows="14"></textarea>
                    </div>
                    <button type="button" id="button_review" class="btn btn-primary">Done, review the email</button>
                </form>
            </div>

            <div class="step" id="step_review" style="display:none;">
                <a name="review"></a><h1>Step 3: Review email</h1>
                <p class="text-muted">
                    The email based on the first data line is shown below. Carefully check if the addressing and dynamic fields are correct.<br/>
                    If everything is OK, press the button to send <i>all</i> emails.</br>
                    If something is wrong, modify the email and review again.
                </p>
                <hr>
                <blockquote id="email_review_content">
                    
                </blockquote>

                <button type="button" id="button_send_emails" class="btn btn-danger">Looks good, send all emails</button>
            </div>
        </div>


    <script type="text/javascript">
        window.csv2mail = new Object(); // Holds some global stuff

        $(document).ready(function() {
            $('#form_csv').ajaxForm(function(data) {
                feedback = jQuery.parseJSON(data);
                if ('error' in feedback) {
                    alert("Error: " + feedback.error);
                    return;
                }

                window.csv2mail.fields = {};
                window.csv2mail.data = feedback.data;

                // Build csv preview
                var html_thead = "<tr>";
                var html_tags = "";
                var html_recipient_field_select = "";
                for (i=0; i<feedback.column_names.length; i++) {
                    html_thead += "<th>" + feedback.column_names[i] + "</th>";
                    html_tags += "<span class=\"label label-default\">{" + feedback.column_names[i] + "}</span>&nbsp;";
                    window.csv2mail.fields["{"+feedback.column_names[i]+"}"] = i;
                    html_recipient_field_select += "<option value=\"" + i + "\">" + feedback.column_names[i] + "</option>";
                }
                html_thead += "</tr>";
                var html_tbody = "";
                for (row=0; row<feedback.data.length; row++) {
                    html_tbody += "<tr>";
                    for (col=0; col<feedback.data[row].length; col++) {
                        html_tbody += "<td>" + feedback.data[row][col] + "</td>";
                    }
                    html_tbody += "</tr>";
                }

                $('.step').hide();
                $('#available_tags').html(html_tags);
                $('#table_csv_preview thead').html(html_thead);
                $('#table_csv_preview tbody').html(html_tbody);
                $('#email_recipient_field').html(html_recipient_field_select);
                $('#step_preview').show();
                $('#step_compose').show();
                $('html,body').animate({scrollTop: $('a[name=compose]').offset().top},'slow');
            });

            $('#button_review').on("click", review_email);
        });

        function replace_dynamic_fields(input_string, data_row) {
            for (var dynamic_field in window.csv2mail.fields) {
                input_string = input_string.split(dynamic_field).join(window.csv2mail.data[data_row][window.csv2mail.fields[dynamic_field]]);
            }

            return input_string;
        }

        function review_email() {
            var email_name = $('#email_name').val();
            var email_address = $('#email_address').val();
            var email_recipient_field = parseInt($('#email_recipient_field').val());
            var email_subject = $('#email_subject').val();
            var email_body = $('#email_body').val();

            if (email_name == '') {
                alert("First enter a sender name");
                return;
            }
            if (email_address == '') {
                alert("First enter a sender address");
                return;
            }
            if (email_subject == '') {
                alert("First enter a subject line");
                return;
            }
            if (email_body == '') {
                alert("First enter a message body");
                return;
            }

            var review_html = "From: " + email_name + " &lt;" + email_address + "&gt;<br/>";
            review_html += "To: " + window.csv2mail.data[0][email_recipient_field] + "<br/>";
            review_html += "Subject: " + replace_dynamic_fields(email_subject, 0) + "<br/><br/>";
            review_html += replace_dynamic_fields(email_body, 0).split("\n").join("<br/>");

            $('#email_review_content').html(review_html);
            $('#step_review').show();
            $('html,body').animate({scrollTop: $('a[name=review]').offset().top},'slow');
        }
    </script>
 
    </body>
</html>
