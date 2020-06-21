<?php
// this example uses a wrapper around PHP's Curl Extension: https://github.com/shuber/curl
require('lib/curl.php');
require('lib/curl_response.php');

require('EgnyteClient.php');

// the following should be modified to match your Egnyte domain name, folder where you would like to
// upload (with trailing slash) and oauth token for the user account the upload will be performed with
$domain = 'precipiodx';
$folder = '/Shared/Case Results/1_Pending Cases/shaantests';
$oauthToken = '32d3ynctm6s8bhyjtcabnn2g';

// get the file contents and name from the upload (where the name of the file input posted to the page is 'filedata')
$fileBinaryContents = file_get_contents($_FILES['filedata']['tmp_name']);
$fileName = $_FILES['filedata']['name'];

// instantiate an Egnyte Client with the domain and oAuth token for the user with which the upload will be performed
$egnyte = new EgnyteClient($domain, $oauthToken);

// perform the upload and get the response from the server
$response = $egnyte->uploadFile($folder, $fileName, $fileBinaryContents);

?>
<!DOCTYPE html>
<html>
<head>
	<title>Anonymous Upload Result</title>
</head>
<body>

<h1>Anonymous Upload Result</h1>

<?php
// errors are HTTP status codes 400 and greater
if($response->isError()) {
	?>
	Error uploading file.  Here's the detailed output from the API request:<br><br>
	<pre><?=htmlspecialchars($response->body, ENT_QUOTES);?></pre>
	<pre><?php print_r($response->getErrorDetails());?></pre>
	<?php
} else {
	?>
	Successfully uploaded <?=htmlspecialchars($fileName, ENT_QUOTES);?> to <?=htmlspecialchars($folder, ENT_QUOTES);?><br><br>
	File Metadata:
	<pre><?php print_r($egnyte->getFileDetails($folder . $fileName)->getDecodedJSON());?></pre>
	<?php
}
?>
<br><br>
<a href="index.php">Back to upload form</a>

</body>
</html>
