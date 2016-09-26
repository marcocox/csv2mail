csv2mail
========

Read a csv file & send bulk dynamical emails through a simple web interface.

This simple php script lets you send templated emails in bulk based on a CSV file. The recipients' addresses and the values of the dynamic fields are read from a CSV file. It works with all CSV formats as long as every row is translated in an email and every dynamic field is containted within its own column.

Example/screenshots
-------------------

CSV file:
```
firstname,lastname,email,dummy_column
John,Doe,johndoe@some-domain.com,0
Bob,Doe,bobdoe@other-domain.com,3
```
![Screenshot step 1](https://www.marcocox.com/images/csv2mail/csv2mail_screenshot1.png)

![Screenshot step 2](https://www.marcocox.com/images/csv2mail/csv2mail_screenshot2.png)

![Screenshot step 3](https://www.marcocox.com/images/csv2mail/csv2mail_screenshot3.png)

Note on security
----------------

This tool should **not** be publicly available as it allows bulk emailing and has **no authentication built in**. It's not written with security in mind, so putting it in a public place is a great way to get your server hacked and blacklisted. You should at least put it behind basic http authentication.

Installation
------------

1. `git clone --recursive git@github.com:marcocox/csv2mail.git`
2. Duplicate `config.template.php` to `config.php` and insert your SMTP credentials in that file.
3. Browse to the correct address to access the web interface and get started.
