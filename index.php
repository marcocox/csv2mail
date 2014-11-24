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
                        <textarea class="form-control" id="email_body" name="email_body" cols="40" rows="20"></textarea>
                    </div>
                    <button type="button" class="btn btn-primary">Review email &amp; continue</button>
                </form>
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
        });
    </script>
 
    </body>
</html>
