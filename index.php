<?php
//////////////////////////////
// Action dispatcher
//////////////////////////////
if ($_SERVER['REQUEST_METHOD'] != 'GET') {
    $actions = array('parse_csv', 'send_mail');
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

function send_mail() {
    if (!isset($_POST['from_name']) || $_POST['from_name']=='') {
        echo json_encode(array('error' => 'The sender name is not passed.'));
        return;
    }
    if (!isset($_POST['from_address']) || $_POST['from_address']=='') {
        echo json_encode(array('error' => 'The sender email is not passed.'));
        return;
    }
    if (!isset($_POST['recipient']) || $_POST['recipient']=='') {
        echo json_encode(array('error' => 'The recipient email is not passed.'));
        return;
    }
    if (!isset($_POST['subject']) || $_POST['subject']=='') {
        echo json_encode(array('error' => 'The subject is not passed.'));
        return;
    }
    if (!isset($_POST['body']) || $_POST['body']=='') {
        echo json_encode(array('error' => 'The email body is not passed.'));
        return;
    }

    $headers = array();
    $headers[] = 'From: ' . $_POST['from_name'] . ' <' . $_POST['from_address'] . '>';
    $headers[] = 'To: ' . $_POST['recipient'];
    if (isset($_POST['bcc']) && $_POST['bcc']) $headers[] =  "Bcc: " . $_POST['bcc'];
    if (isset($_POST['cc']) && $_POST['cc']) $headers[] =  "Cc: " . $_POST['cc'];
    $headers[] = 'X-Mailer: php';

    mail($_POST['recipient'], $_POST['subject'], $_POST['body'], implode("\r\n", $headers));
    echo json_encode(array());
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
                    All text input fields may contain dynamic fields. Use {field_name} where you want to insert the value of a dynamic field.<br/>
                    Available dynamic fields: <div id="available_tags"></div>
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
                        <label for="email_cc">CC:</label>
                        <input type="email" class="form-control" id="email_cc" name="email_cc">
                        <p class="help-block">Optionally add one or more CC address(es). Leave empty to ignore.</p>
                    </div>
                    <div class="form-group">
                        <label for="email_bcc">BCC:</label>
                        <input type="email" class="form-control" id="email_bcc" name="email_bcc">
                        <p class="help-block">Optionally add one or more CC address(es). Leave empty to ignore.</p>
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

            <div class="modal fade" id="modal_progress">
              <div class="modal-dialog">
                <div class="modal-content">
                  <div class="modal-header">
                    <h4 class="modal-title">Sending in progress (one email per second)</h4>
                  </div>
                  <div class="modal-body">
                    <p>The following emails have been sent:</p>
                    <p id="sent_list"></p>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal" id="button_progress_close" style="display:none;">Close</button>
                  </div>
                </div><!-- /.modal-content -->
              </div><!-- /.modal-dialog -->
            </div><!-- /.modal -->
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
            $('#button_send_emails').on("click", send_emails);
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
            var email_cc = $('#email_cc').val();
            var email_bcc = $('#email_bcc').val();
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

            var review_html = "From: " + replace_dynamic_fields(email_name, 0) + " &lt;" + replace_dynamic_fields(email_address, 0) + "&gt;<br/>";
            review_html += "To: " + window.csv2mail.data[0][email_recipient_field] + "<br/>";
            if (email_cc != "") review_html += "Cc: " + replace_dynamic_fields(email_cc, 0) + "<br/>";
            if (email_bcc != "") review_html += "Bcc: " + replace_dynamic_fields(email_bcc, 0) + "<br/>";
            review_html += "Subject: " + replace_dynamic_fields(email_subject, 0) + "<br/><br/>";
            review_html += replace_dynamic_fields(email_body, 0).split("\n").join("<br/>");

            $('#email_review_content').html(review_html);
            $('#step_review').show();
            $('html,body').animate({scrollTop: $('a[name=review]').offset().top},'slow');
        }

        function send_emails() {
            if (!confirm("Are you sure you want to send all emails?")) return;
            $('#sent_list').html("");
            $('#button_progress_close').hide();
            $('#modal_progress').modal('show');
            send_email(0);
        }

        function send_email(idx) {
            // This function automatically sets a timeout to send the next email
            // So calling send_email(3) will send email 3, 4, 5, ... , N
            var post_data = {};
            post_data['from_name'] = replace_dynamic_fields($('#email_name').val(), idx);
            post_data['from_address'] = replace_dynamic_fields($('#email_address').val(), idx);
            post_data['recipient'] = window.csv2mail.data[idx][parseInt($('#email_recipient_field').val())];
            post_data['cc'] = replace_dynamic_fields($('#email_cc').val(), idx);
            post_data['bcc'] = replace_dynamic_fields($('#email_bcc').val(), idx);
            post_data['subject'] = replace_dynamic_fields($('#email_subject').val(), idx);
            post_data['body'] = replace_dynamic_fields($('#email_body').val(), idx);

            $.post("index.php?action=send_mail", post_data)
                .done(function(data) {
                    feedback = jQuery.parseJSON(data);
                    if ('error' in feedback) {
                        $('#sent_list').html($('#sent_list').html() + "<br/><b>ABORTED DUE TO ERROR:</b><br/>" + feedback.error);
                        $('#button_progress_close').show(); 
                        return;
                    } else {
                        $('#sent_list').html($('#sent_list').html() + '&nbsp;' + post_data['recipient']);
                        next_idx = idx + 1;
                        if (next_idx < window.csv2mail.data.length) {
                            setTimeout(function(){send_email(next_idx);}, 1000);
                        } else {
                            $('#sent_list').html($('#sent_list').html() + "<br/><b>ALL DONE!</b>");
                            $('#button_progress_close').show(); 
                        }
                    }
                });
        }
    </script>
 
    </body>
</html>
