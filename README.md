# Egnyte yii2 component 

## How to use it
```
use common\components\egnyte\EgnyteClient;

// the following should be modified to match your Egnyte domain name, folder where you would like to
// upload (with trailing slash) and oauth token for the user account the upload will be performed with
$domain = 'uexel';
$folder = '/Shared/Documents';
$oauthToken = '237492874';

// get the file contents and name from the upload (where the name of the file input posted to the page is 'filedata')
$fileBinaryContents = file_get_contents($_FILES['filedata']['tmp_name']);
$fileName = $_FILES['filedata']['name'];

// instantiate an Egnyte Client with the domain and oAuth token for the user with which the upload will be performed
$egnyte = new EgnyteClient($domain, $oauthToken);

// perform the upload and get the response from the server
$response = $egnyte->uploadFile($folder, $fileName, $fileBinaryContents);

?>
