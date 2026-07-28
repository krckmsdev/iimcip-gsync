=== Career Cloud Sync ===
Contributors: iimcip
Tags: contact form 7, cf7, google drive, google sheets, recruitment, hr, job application
Requires at least: 6.2
Tested up to: 6.2.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically sync Contact Form 7 job applications with Google Drive and Google Sheets.

== Description ==

Career Cloud Sync is a WordPress plugin that automates the entire recruitment workflow by integrating Contact Form 7, Google Drive, and Google Sheets.

Instead of manually downloading resumes, organizing applicant folders, and maintaining spreadsheets, the plugin performs these tasks automatically whenever a candidate submits a job application.

### Features

* Contact Form 7 integration
* Google Drive integration
* Google Sheets integration
* Google OAuth authentication
* Google Connection Test
* Job-based folder mapping
* Weekly Google Sheet generation
* Automatic applicant folder creation using the applicant's email address
* Automatic upload of Resume/CV, Cover Letter, and Portfolio
* Direct Google Drive links stored in Google Sheets
* Searchable synchronization logs
* WordPress admin dashboard

### How it Works

1. A candidate submits a Contact Form 7 application.
2. The plugin identifies the selected job position.
3. The mapped Google Drive folders are loaded.
4. A folder named using the applicant's email address is created inside the configured Attachments folder.
5. The Resume/CV, Cover Letter, and Portfolio are uploaded.
6. A weekly Google Sheet is created automatically if one does not already exist.
7. The applicant's information is appended as a new row.
8. Direct Google Drive links to all uploaded documents are recorded in the corresponding spreadsheet row.
9. Every operation is logged for monitoring and troubleshooting.

== Installation ==

1. Upload the `career-cloud-sync` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Create a Google Cloud Project.
4. Enable the Google Drive API and Google Sheets API.
5. Configure the OAuth Consent Screen.
6. Create OAuth 2.0 credentials.
7. Generate a Refresh Token.
8. Configure the plugin from **Career Cloud Sync → Settings**.
9. Verify the Google connection.
10. Configure Folder Mapping.
11. Submit a test Contact Form 7 application.

== Frequently Asked Questions ==

= Does this plugin require Contact Form 7? =

Yes. Contact Form 7 is required.

= Which Google APIs are required? =

Google Drive API and Google Sheets API.

= Does the plugin create Google Sheets automatically? =

Yes. A new Google Sheet is created automatically for each week. All applications submitted during that week are appended to the same sheet.

= How are applicant files organized? =

Each job position is mapped to its own Google Drive folders. Inside the configured Attachments folder, the plugin creates a dedicated folder using the applicant's email address and uploads the Resume/CV, Cover Letter, and Portfolio.

= What information is stored in Google Sheets? =

The weekly Google Sheet stores applicant information together with direct Google Drive links to the uploaded Resume/CV, Cover Letter, and Portfolio.

== Screenshots ==

1. Settings page
2. Folder Mapping
3. Sync Logs
4. Weekly Google Sheet
5. Google Drive applicant folders

== Changelog ==

= 1.0.0 =

* Initial release.
* Contact Form 7 integration.
* Google Drive integration.
* Google Sheets integration.
* Weekly Google Sheet creation.
* Job folder mapping.
* Applicant folder creation.
* Automatic Resume/CV, Cover Letter, and Portfolio uploads.
* Google Drive links stored in Google Sheets.
* Sync Logs.