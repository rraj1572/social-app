<?php
ini_set('display_errors', 1);
class FileUploader
{
    public function uploadImage($encoded_string, $name, $id)
    {
        $response        = array();
        $file_upload_url = 'http://' . HOST . '/socialapp-api/';
        $image_path      = 'uploads/images/' . $name . '-' . rand(1, 10000) . '-' . rand(10, 50000) . '-' . $id . '.png';
        $decoded_string  = base64_decode($encoded_string);
        $file            = fopen($image_path, 'wb');
        $isUploaded      = fwrite($file, $decoded_string);
        fclose($file);
        if ($isUploaded > 0) {
            $response["image_link"] = $image_path;
            $response["image_path"] = $file_upload_url . $image_path;
            $response["error"]      = false;
        } else {
            $response["isUploaded"] = $isUploaded;
            $response["error"] = true;
            $response["code"]  = UNKNOWN_ERROR;
        }
        return $response;
    }
}
?>
