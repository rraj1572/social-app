<?php

error_reporting(E_ALL);
ini_set('display_errors', 'On');

require_once './include/db_handler.php';
require '././libs/Slim/Slim.php';
require_once './include/upload.php';
require '././include/config.php';

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

$app->get('/user/profilePhoto/:username', function($username) use ($app)
{
    $db        = new DbHandler();
    $imageLink = $db->getImageUri($username);
    if ($imageLink != null) {
        $image = file_get_contents($imageLink);
        $app->response()->header('Content-Type', 'content-type: image/jpg');
        echo $image;
    } else {
        echoRespnse(200, "No image available");
    }
});

$app->post('/user/update_picture', 'authenticate', function() use ($app)
{
    global $user_id, $username, $profileName;
    $db       = new DbHandler();
    $uploader = new FileUploader();
    $user     = $db->getUserByUsername($username);
    $image    = $app->request->params('image');
    $response = array();
    if ($image != null) {
        $response = $uploader->uploadImage($image, rand(1, 10000), $user_id);
        if ($response["error"] == false) {
            $res                      = $db->updatePhotoLink($user_id, $response["image_link"], PROFILE_HOST . $username);
            $response["profilePhoto"] = $res["profilePhoto"];
            echoRespnse(200, $response);
            if ($res["error"] == false) {
                sendFollowersNotifications($user_id, $username, 0, $profileName . " updated " . ($user["gender"] == 2 ? "her" : "his") . " profile picture.");
            }
        }
    } else {
        $response["error"] = true;
        echoRespnse(200, $response);
    }
});

$app->post('/post/report/:id', 'authenticate', function($postId) use ($app)
{
    global $user_id;
    $db     = new DbHandler();
    $action = $app->request->params('action');
    $reason = $app->request->params('reason');
    if ($action != NULL && $reason != null) {
        $response = $db->reportPost($user_id, $postId, $action, $reason);
    } else {
        $response["error"] = true;
    }
    echoRespnse(200, $response);
});

$app->post('/user/register', function() use ($app)
{
    $response = array();
    verifyRequiredParams(array(
        'name',
        'username',
        'email',
        'password'
    ));
    $name     = $app->request->post('name');
    $username = $app->request->post('username');
    $email    = $app->request->post('email');
    $password = $app->request->post('password');
    validateEmail($email);
    $db       = new DbHandler();
    $response = $db->createUser($name, $username, $email, $password);
    if ($response["error"] == false) {
      $user_id = $response["account"];
      $user_id = $user_id["id"];
      $relation    = $db->getRelation($user_id, 1);
      if ($relation["isFollowing"] != 1) {
          $db->followUser($user_id, 1);
      }
    }
    echoRespnse(200, $response);
});

$app->post('/user/login', function() use ($app)
{
    verifyRequiredParams(array(
        'username',
        'password'
    ));
    $username = $app->request->post('username');
    $password = $app->request->post('password');
    $db       = new DbHandler();
    $response = $db->userLogin($username, $password);
    echoRespnse(200, $response);
});

$app->post('/user/authorize', 'authenticate', function() use ($app)
{
    global $user_id;
    $db              = new DbHandler();
    $user            = $db->getUser($user_id);
    $registration_id = $app->request->post('registration_id');
    $version         = $app->request->post('version');
    $response        = array();
    $acc             = $db->getAccountStatus($user_id);
    if (!$acc["isDisabled"]) {
        if ($user == NULL) {
            $response["error"] = true;
        } else {
            $response["error"] = false;
            $response["user"]  = $user;
            if ($version != "") {
              $db->updateVersion($user_id, $version);
            }
            if ($registration_id != "") {
                $gcm = $db->updateGCM($user_id, $registration_id);
                if ($gcm["error"] == false) {
                    updateNotification($user_id);
                    updateMessages($user_id);
                }
            }
        }
    } else {
        $response["error"]  = true;
        $response["reason"] = $acc["disableReason"];
        $response["code"]   = ACCOUNT_DISABLED;
    }
    echoRespnse(200, $response);
});

$app->post('/account/delete', 'authenticate', function() use ($app)
{
    global $user_id;
    $db       = new DbHandler();
    $response = $db->deleteAccount($user_id);
    echoRespnse(200, $response);
});

$app->post('/user/settings', 'authenticate', function() use ($app)
{
    global $user_id;
    $db           = new DbHandler();
    $name         = $app->request->post('name');
    $about        = $app->request->post('about');
    $relationship = $app->request->post('relationship');
    $gender       = $app->request->post('gender');
    $location     = $app->request->post('location');
    $password     = $app->request->post('password');
    $longitude    = $app->request->post('longitude');
    $latitude     = $app->request->post('latitude');
    $response     = array();
    if ($name != null) {
        $response += $db->updateName($user_id, $name);
    }
    if ($about != null) {
        $response += $db->updateAbout($user_id, $about);
    }
    if ($relationship != null) {
        $response += $db->updateRelationship($user_id, $relationship);
    }
    if ($gender != null) {
        $response += $db->updateGender($user_id, $gender);
    }
    if ($location != null) {
        $response += $db->updateLocation($user_id, $location);
    }
    if ($password != null) {
        $response += $db->updatePassword($user_id, $password);
    }
    if ($longitude != null && $latitude != null) {
      $response += $db->updateLocationPoints($user_id, $longitude, $latitude);
    }
    $response["error"] = false;
    echoRespnse(200, $response);
});

$app->get('/users/nearby', 'authenticate', function() use ($app) {
  global $user_id;
  $db                = new DbHandler();
  $myUser = $db->getUser($user_id);
  $result            = $db->getNearby($myUser["longitude"], $myUser["latitude"]);
  $response["error"] = false;
  $response['users'] = array();
  while ($u = $result->fetch(PDO::FETCH_ASSOC)) {
      if (!$db->isUserBlocked($u["id"], $user_id)) {
        if ($u["id"] != $user_id) {
        $dUser = $db->getUser($u["id"]);
        $user                  = array();
        $user["id"]            = $dUser["id"];
        $user["username"]      = $dUser["username"];
        $user["name"]          = $dUser["name"];
        $user["email"]         = $dUser["email"];
        $user["creation"]      = $dUser["created_At"];
        $user["icon"]          = $dUser["profilePhoto"];
        $user["isVerified"]    = $dUser["isVerified"];
        $user["distance"]      = $u["distance"];
        array_push($response["users"], $user);
      }
    }
  }
  echoRespnse(200, $response);
});

$app->get('/users/directory/:toFind', 'authenticate', function($toFind) use ($app)
{
    global $user_id;
    $db                = new DbHandler();
    $result            = $db->searchUsers($toFind, $user_id);
    $response["error"] = false;
    $response['users'] = array();
    while ($u = $result->fetch(PDO::FETCH_ASSOC)) {
        if (!$db->isUserBlocked($u["id"], $user_id)) {
            $user                  = array();
            $user["id"]            = $u["id"];
            $user["username"]      = $u["username"];
            $user["name"]          = $u["name"];
            $user["email"]         = $u["email"];
            $user["creation"]      = $u["created_At"];
            $user["icon"]          = $u["profilePhoto"];
            $user["mutualFriends"] = "0";
            $user["isVerified"]    = $u["isVerified"];
            $user += $db->getRelation($u["id"], $user_id);
            if ($user["isFriend"] == 1) {
                $user["location"] = $u["location"] == null ? "" : $u["location"];
                ;
            }
            array_push($response["users"], $user);
        }
    }
    echoRespnse(200, $response);
});

$app->put('/user/unblock/:id', 'authenticate', function($id) use ($app)
{
    global $user_id;
    $db = new DbHandler();
    if ($db->isUserBlocked($id, $user_id)) {
        $response = $db->removeBlock($id, $user_id);
    }
    echoRespnse(200, $response);
});

$app->get('/user/blockList', 'authenticate', function() use ($app)
{
    global $user_id;
    $db                = new DbHandler();
    $result            = $db->getBlockList($user_id);
    $response["error"] = false;
    $response["list"]  = array();
    while ($b = $result->fetch(PDO::FETCH_ASSOC)) {
        $user             = array();
        $userAcc          = $db->getUser($b["blocked_user"]);
        $user["user_id"]  = $userAcc["id"];
        $user["username"] = $userAcc["username"];
        $user["name"]     = $userAcc["name"];
        $user["creation"] = $b["creation"];
        array_push($response["list"], $user);
    }
    echoRespnse(200, $response);
});

$app->get('/user/post/:id', 'authenticate', function($postId) use ($app)
{
    global $user_id;
    $db       = new DbHandler();
    $feed     = $db->getPost($user_id, $postId);
    $response = array();
    if ($feed != null) {
        if (!$db->isUserBlocked($user_id, $feed["user_id"])) {
            if (isUserClearAudience($user_id, $feed["user_id"], $feed["audience"] == NULL ? 0 : $feed["audience"])) {
                $data = array();

                // User
                $data['user_id']      = $feed["user_id"];
                $data["username"]     = $feed["username"];
                $data["name"]         = $feed["name"];
                $data["profilePhoto"] = $feed["profilePhoto"];
                $data["isVerified"]   = $feed["isVerified"];

                // Post
                $post                 = array();
                $post["postId"]       = $feed["postId"];
                $post["type"]         = $feed["type"];
                $post["content"]      = $feed["content"];
                $post["description"]  = $feed["description"];
                $post["creation"]     = $feed["creation"];
                $post["isLiked"]      = $feed["isLiked"] == NULL ? 0 : $feed["isLiked"];
                $post["likes"]        = $feed["likes"];
                $post["shares"]       = $feed["isShared"] == 1 ? $feed["shared_shares"] : $feed["shares"];
                $post["audience"]     = $feed["audience"] == NULL ? 0 : $feed["audience"];
                $post["comments"]     = $feed["comments"];
                $post["isShared"]     = $feed["isShared"];
                $post["shareId"]      = $feed["shared_id"];
                $post["sharedPostId"] = $feed["shared_post_id"];
                $data["post"]         = $post;

                // Original Poster (if any)
                if ($post["isShared"] == 1) {
                    $poster               = array();
                    $sharedUser           = $db->getUser($post["shareId"]);
                    $poster['user_id']    = $sharedUser["id"];
                    $poster["username"]   = $sharedUser["username"];
                    $poster["name"]       = $sharedUser["name"];
                    $data["profilePhoto"] = $sharedUser["profilePhoto"];
                    $data["isVerified"]   = $sharedUser["isVerified"];
                    $data["sharedUser"]   = $poster;
                }
                $response["error"] = false;
                $response["feed"]  = $data;
            } else {
                $response["error"] = true;
            }
        } else {
            $response["error"] = true;
        }
    } else {
        $response["error"] = true;
    }
    echoRespnse(200, $response);
});

$app->get('/user/trend/:from', 'authenticate', function($from) use ($app)
{
    global $user_id;
    $db                = new DbHandler();
    $feeds             = $db->retrieveTrendFeed($user_id, $from);
    $response["error"] = false;
    $response["user"]  = $db->getUser($user_id);
    $response["feeds"] = array();
    while ($feed = $feeds->fetch(PDO::FETCH_ASSOC)) {
        $isBlocked = $db->isUserBlocked($user_id, $feed["user_id"]);
        if (!$isBlocked) {
            if ($feed["shared_id"] != $user_id) {
                if (isUserClearAudience($user_id, $feed["user_id"], $feed["audience"] == NULL ? 0 : $feed["audience"])) {
                    $data = array();

                    // User
                    $data['user_id']      = $feed["user_id"];
                    $data["username"]     = $feed["username"];
                    $data["name"]         = $feed["name"];
                    $data["profilePhoto"] = $feed["profilePhoto"];
                    $data["isVerified"]   = $feed["isVerified"];

                    // Post
                    $post                   = array();
                    $post["postId"]         = $feed["postId"];
                    $post["type"]           = $feed["type"];
                    $post["content"]        = $feed["content"];
                    $post["description"]    = $feed["description"];
                    $post["creation"]       = $feed["creation"];
                    $post["isLiked"]        = $feed["isLiked"] == NULL ? 0 : $feed["isLiked"];
                    $post["likes"]          = $feed["likes"];
                    $post["shares"]         = $feed["isShared"] == 1 ? $feed["shared_shares"] : $feed["shares"];
                    $post["audience"]       = $feed["audience"] == NULL ? 0 : $feed["audience"];
                    $post["isPostFollowed"] = $feed["isPostFollowed"] == "0" ? 0 : 1;
                    $post["comments"]       = $feed["comments"];
                    $post["isShared"]       = $feed["isShared"];
                    $post["shareId"]        = $feed["shared_id"];
                    $post["sharedPostId"]   = $feed["shared_post_id"];
                    $data["post"]           = $post;

                    // Original Poster (if any)
                    if ($post["isShared"] == 1) {
                        $poster               = array();
                        $sharedUser           = $db->getUser($post["shareId"]);
                        $poster['user_id']    = $sharedUser["id"];
                        $poster["username"]   = $sharedUser["username"];
                        $poster["name"]       = $sharedUser["name"];
                        $data["profilePhoto"] = $sharedUser["profilePhoto"];
                        $data["isVerified"]   = $sharedUser["isVerified"];
                        $data["sharedUser"]   = $poster;
                    }
                    array_push($response["feeds"], $data);
                }
            }
        }
    }
    echoRespnse(200, $response);
});

$app->get('/user/feed/:from', 'authenticate', function($from) use ($app)
{
    global $user_id;
    $db                = new DbHandler();
    $feeds             = $db->retrieveFeed($user_id, $from);
    $response["error"] = false;
    $response["user"]  = $db->getUser($user_id);
    $response["feeds"] = array();
    while ($feed = $feeds->fetch(PDO::FETCH_ASSOC)) {
        $isBlocked = $db->isUserBlocked($user_id, $feed["user_id"]);
        if (!$isBlocked) {
            if ($feed["shared_id"] != $user_id) {
                if (isUserClearAudience($user_id, $feed["user_id"], $feed["audience"] == NULL ? 0 : $feed["audience"])) {
                    $data = array();

                    // User
                    $data['user_id']      = $feed["user_id"];
                    $data["username"]     = $feed["username"];
                    $data["name"]         = $feed["name"];
                    $data["profilePhoto"] = $feed["profilePhoto"];
                    $data["isVerified"]   = $feed["isVerified"];

                    // Post
                    $post                   = array();
                    $post["postId"]         = $feed["postId"];
                    $post["type"]           = $feed["type"];
                    $post["content"]        = $feed["content"];
                    $post["description"]    = $feed["description"];
                    $post["creation"]       = $feed["creation"];
                    $post["isLiked"]        = $feed["isLiked"] == NULL ? 0 : $feed["isLiked"];
                    $post["likes"]          = $feed["likes"];
                    $post["shares"]         = $feed["isShared"] == 1 ? $feed["shared_shares"] : $feed["shares"];
                    $post["audience"]       = $feed["audience"] == NULL ? 0 : $feed["audience"];
                    $post["isPostFollowed"] = $feed["isPostFollowed"] == "0" ? 0 : 1;
                    $post["comments"]       = $feed["comments"];
                    $post["isShared"]       = $feed["isShared"];
                    $post["shareId"]        = $feed["shared_id"];
                    $post["sharedPostId"]   = $feed["shared_post_id"];
                    $data["post"]           = $post;

                    // Original Poster (if any)
                    if ($post["isShared"] == 1) {
                        $poster               = array();
                        $sharedUser           = $db->getUser($post["shareId"]);
                        $poster['user_id']    = $sharedUser["id"];
                        $poster["username"]   = $sharedUser["username"];
                        $poster["name"]       = $sharedUser["name"];
                        $data["profilePhoto"] = $sharedUser["profilePhoto"];
                        $data["isVerified"]   = $sharedUser["isVerified"];
                        $data["sharedUser"]   = $poster;
                    }
                    array_push($response["feeds"], $data);
                }
            }
        }
    }
    echoRespnse(200, $response);
});

$app->get('/user/info/:id', 'authenticate', function($username) use ($app)
{
    global $user_id;
    $response  = array();
    $db        = new DbHandler();
    $from      = $app->request->params('from');
    $user      = $db->getUserByUsername($username);
    $isBlocked = $db->isUserBlocked($user_id, $user["id"]);
    if ($user != NULL) {
        if (!$isBlocked) {
            $response             = $user;
            $relation             = $db->getRelation($user_id, $response["id"]);
            $response["relation"] = $relation;
            $response["error"]    = false;
        } else {
            $response['error'] = true;
        }
    } else {
        $response['error'] = true;
    }
    echoRespnse(200, $response);
});

$app->get('/feed/media/:id', 'authenticate', function($username) use ($app)
{
    global $user_id;
    $isPhotos  = $app->request->params('isPhotos');
    $isVideos  = $app->request->params('isVideos');
    $db        = new DbHandler();
    $user      = $db->getUserByUsername($username);
    $isBlocked = $db->isUserBlocked($user_id, $user["id"]);
    if ($user != NULL) {
        if (!$isBlocked) {
            $feeds             = $db->retrieveMediaFeed($user["id"], $isPhotos == 1 ? true : false, $isVideos == 2 ? true : false);
            $response["feeds"] = array();
            while ($feed = $feeds->fetch(PDO::FETCH_ASSOC)) {
                if (isUserClearAudience($user_id, $user["id"], $feed["audience"] == NULL ? 0 : $feed["audience"])) {
                    $data            = array();
                    $post            = array();
                    $post["postId"]  = $feed["id"];
                    $post["content"] = $feed["content"];
                    $post["type"]    = $feed["type"];
                    $data["post"]    = $post;
                    array_push($response["feeds"], $data);
                }
            }
            $response["error"] = false;
        } else {
            $response['error'] = true;
        }
    } else {
        $response['error'] = true;
    }
    echoRespnse(200, $response);
});

$app->get('/feed/:id', 'authenticate', function($username) use ($app)
{
    global $user_id;
    $db        = new DbHandler();
    $from      = $app->request->params('from');
    $user      = $db->getUserByUsername($username);
    $isBlocked = $db->isUserBlocked($user_id, $user["id"]);
    if ($user != NULL) {
        if (!$isBlocked) {
            $feeds             = $db->retrieveMyFeed($user["id"], $user_id, $from);
            $response["feeds"] = array();
            while ($feed = $feeds->fetch(PDO::FETCH_ASSOC)) {
                if (isUserClearAudience($user_id, $user["id"], $feed["audience"] == NULL ? 0 : $feed["audience"])) {
                    $data = array();

                    // Post
                    $post                 = array();
                    $post["postId"]       = $feed["postId"];
                    $post["type"]         = $feed["type"];
                    $post["content"]      = $feed["content"];
                    $post["description"]  = $feed["description"];
                    $post["creation"]     = $feed["creation"];
                    $post["isLiked"]      = $feed["isLiked"] == NULL ? 0 : $feed["isLiked"];
                    $post["likes"]        = $db->getLikesCount($post["postId"]);
                    $post["shares"]       = $feed["shares"];
                    $post["audience"]     = $feed["audience"] == NULL ? 0 : $feed["audience"];
                    $post["comments"]     = $feed["comments"];
                    $post["isShared"]     = $feed["isShared"];
                    $post["shareId"]      = $feed["shared_id"];
                    $post["sharedPostId"] = $feed["shared_post_id"];
                    $data["post"]         = $post;

                    // Original Poster (if any)
                    if ($post["isShared"] == 1) {
                        $poster             = array();
                        $sharedUser         = $db->getUser($post["shareId"]);
                        $poster['user_id']  = $sharedUser["id"];
                        $poster["username"] = $sharedUser["username"];
                        $poster["name"]     = $sharedUser["name"];
                        $data["sharedUser"] = $poster;
                    }
                    array_push($response["feeds"], $data);
                }
            }
            $response["error"] = false;
        } else {
            $response['error'] = true;
        }
    } else {
        $response['error'] = true;
    }
    echoRespnse(200, $response);
});

$app->get('/user/friends/:user', 'authenticate', function($username) use ($app)
{
    global $user_id;
    $db = new DbHandler();
    if ($username == ":") {
        $friends             = $db->retrieveFriends($user_id);
        $response["error"]   = false;
        $response["friends"] = array();
        $s["friends"]        = array();
        while ($friend = $friends->fetch(PDO::FETCH_ASSOC)) {
            $f = ($friend["user_id"] == $user_id ? $friend["user_with"] : $friend["user_id"]);
            $f = $db->getUser($f);
            if ($f["id"] != null) {
                $data               = array();
                $data["id"]         = $f["id"];
                $data["name"]       = $f["name"];
                $data["username"]   = $f["username"];
                $data["icon"]       = $f["profilePhoto"];
                $data["creation"]   = $friend["creation"];
                $data["location"]   = $f["location"] == null ? "" : $f["location"];
                $data["isVerified"] = $f["isVerified"] == null ? 0 : $f["isVerified"];
                $data += $db->getRelation($f["id"], $user_id);
                array_push($response["friends"], $data);
            }
        }
        echoRespnse(200, $response);
    } else {
        $friends             = $db->getUserByUsername($username);
        $fid                 = $friends["id"];
        $friends             = $db->retrieveFriends($fid);
        $response["error"]   = false;
        $response["friends"] = array();
        while ($friend = $friends->fetch(PDO::FETCH_ASSOC)) {
            $f = ($friend["user_id"] == $fid ? $friend["user_with"] : $friend["user_id"]);
            if (!$db->isUserBlocked($f, $user_id)) {
                $f = $db->getUser($f);
                if ($f["id"] != null) {
                    $data             = array();
                    $data["id"]       = $f["id"];
                    $data["name"]     = $f["name"];
                    $data["username"] = $f["username"];
                    $data["icon"]     = $f["profilePhoto"];
                    $data["creation"] = $friend["creation"];
                    $data["location"] = "";
                    if ($db->isFriend($user_id, $f["id"])) {
                        $data["location"] = $f["location"] == null ? "" : $f["location"];
                    }
                    $data["isVerified"] = $f["isVerified"] == null ? 0 : $f["isVerified"];
                    $data += $db->getRelation($f["id"], $fid);
                    array_push($response["friends"], $data);
                }
            }
        }
        echoRespnse(200, $response);
    }
});

$app->get('/user/followers/:user', 'authenticate', function($username) use ($app)
{
    global $user_id;
    $db = new DbHandler();
    if ($username == ":") {
        $followers             = $db->retrieveFollowers($user_id);
        $response["error"]     = false;
        $response["followers"] = array();
        while ($follower = $followers->fetch(PDO::FETCH_ASSOC)) {
            if (!$db->isUserBlocked($follower["userId"], $user_id)) {
                if ($follower["name"] != null) {
                    $data               = array();
                    $data["id"]         = $follower["id"];
                    $data["name"]       = $follower["name"];
                    $data["username"]   = $follower["username"];
                    $data["icon"]       = $follower["profilePhoto"];
                    $data["creation"]   = $follower["creation"];
                    $data["location"]   = $follower["location"] == null ? "" : $follower["location"];
                    $data["isVerified"] = $follower["isVerified"] == null ? 0 : $follower["isVerified"];
                    array_push($response["followers"], $data);
                }
            }
        }
        echoRespnse(200, $response);
    } else {
        $followers             = $db->getUserByUsername($username);
        $followers             = $followers["id"];
        $followers             = $db->retrieveFollowers($followers);
        $response["error"]     = false;
        $response["followers"] = array();
        while ($follower = $followers->fetch(PDO::FETCH_ASSOC)) {
            if (!$db->isUserBlocked($follower["userId"], $user_id)) {
                if ($follower["name"] != null) {
                    $data               = array();
                    $data["id"]         = $follower["id"];
                    $data["name"]       = $follower["name"];
                    $data["username"]   = $follower["username"];
                    $data["icon"]       = $follower["profilePhoto"];
                    $data["creation"]   = $follower["creation"];
                    $data["location"]   = $follower["location"] == null ? "" : $follower["location"];
                    $data["isVerified"] = $follower["isVerified"] == null ? 0 : $follower["isVerified"];
                    array_push($response["followers"], $data);
                }
            }
        }
        echoRespnse(200, $response);
    }
});

$app->get('/user/followings/:user', 'authenticate', function($username) use ($app)
{
    global $user_id;
    $db = new DbHandler();
    if ($username == ":") {
        $followings             = $db->retrieveFollowings($user_id);
        $response["error"]      = false;
        $response["followings"] = array();
        while ($following = $followings->fetch(PDO::FETCH_ASSOC)) {
            if (!$db->isUserBlocked($following["userId"], $user_id)) {
                if ($following["name"] != null) {
                    $data["id"]         = $following["id"];
                    $data["name"]       = $following["name"];
                    $data["username"]   = $following["username"];
                    $data["icon"]       = $following["profilePhoto"];
                    $data["creation"]   = $following["creation"];
                    $data["location"]   = $following["location"] == null ? "" : $following["location"];
                    $data["isVerified"] = $following["isVerified"] == null ? 0 : $following["isVerified"];
                    array_push($response["followings"], $data);
                }
            }
        }
        echoRespnse(200, $response);
    } else {
        $followings             = $db->getUserByUsername($username);
        $followings             = $followings["id"];
        $followings             = $db->retrieveFollowers($followings);
        $response["error"]      = false;
        $response["followings"] = array();
        while ($following = $followings->fetch(PDO::FETCH_ASSOC)) {
            if (!$db->isUserBlocked($following["userId"], $user_id)) {
                if ($following["name"] != null) {
                    $data["id"]         = $following["id"];
                    $data["name"]       = $following["name"];
                    $data["username"]   = $following["username"];
                    $data["icon"]       = $following["profilePhoto"];
                    $data["creation"]   = $following["creation"];
                    $data["location"]   = $following["location"] == null ? "" : $following["location"];
                    $data["isVerified"] = $following["isVerified"] == null ? 0 : $following["isVerified"];
                    array_push($response["followings"], $data);
                }
            }
        }
        echoRespnse(200, $response);
    }
});

$app->post('/upload', 'authenticate', function() use ($app)
{
    global $user_id;
    $uploader = new FileUploader();
    $image    = $app->request->params('image');
    $response = array();
    if ($image != null) {
        $response = $uploader->uploadImage($image, rand(1, 10000), $user_id);
    } else {
        $response["error"] = true;
    }
    echoRespnse(200, $response);
});

// Post Requests
$app->post('/user/add_post', 'authenticate', function() use ($app)
{
    global $user_id, $username, $profileName;
    $db          = new DbHandler();
    $description = $app->request->params('description');
    $content     = $app->request->params('content');
    $audience    = $app->request->params('audience');
    $type        = $app->request->params('type');
    $response    = $db->addPost($user_id, $audience, $content, $description, $type);
    if ($response["error"] == false) {
        $s = getPostType($type);
        if ($audience != 0) {
          sendFollowersNotifications($user_id, $username, $response["id"], $profileName . " added a new " . $s);
        }
    }
    echoRespnse(200, $response);
});

$app->get('/feed/likes/:from', 'authenticate', function($from) use ($app)
{
    global $user_id;
    $db                = new DbHandler();
    $post_id           = $app->request->params('postId');
    $likes             = $db->retrieveLikes($user_id, $post_id, $from);
    $response["error"] = false;
    $response["likes"] = array();
    while ($like = $likes->fetch(PDO::FETCH_ASSOC)) {
        if (!$db->isUserBlocked($user_id, $like["userId"])) {
            $data                  = array();
            $data["post_id"]       = $like["post_id"];
            $data["username"]      = $like["username"];
            $data["name"]          = $like["name"];
            $data["mutualFriends"] = "0";
            $data["icon"]          = $like["icon"];
            $data["creation"]      = $like["creation"];
            $data["isVerified"]    = $like["isVerified"];
            array_push($response["likes"], $data);
        }
    }
    echoRespnse(200, $response);
});

$app->get('/comment/replies/:commentId', 'authenticate', function($comment_id) use ($app)
{
    global $user_id;
    $db                   = new DbHandler();
    $comments             = $db->retrieveReplies($comment_id);
    $response["error"]    = false;
    $response["comments"] = array();
    while ($comment = $comments->fetch(PDO::FETCH_ASSOC)) {
        if (!$db->isUserBlocked($user_id, $comment["userId"])) {
            $data             = array();
            $data["id"]       = $comment["id"];
            $data["comment_id"]  = $comment["comment_id"];
            $data["username"] = $comment["username"];
            $data["name"]     = $comment["name"];
            $data["icon"]     = $comment["icon"];
            $data["content"]  = $comment["content"];
            $data["creation"] = $comment["creation"];
            array_push($response["comments"], $data);
        }
    }
    echoRespnse(200, $response);
});

$app->get('/feed/comments/:postId', 'authenticate', function($post_id) use ($app)
{
    global $user_id;
    $db                   = new DbHandler();
    $comments             = $db->retrieveComments($post_id);
    $p                    = $db->getPostDetails($post_id);
    $response["error"]    = false;
    $response["isOwnPost"] = $p["user_id"] == $user_id;
    $response["comments"] = array();
    while ($comment = $comments->fetch(PDO::FETCH_ASSOC)) {
        if (!$db->isUserBlocked($user_id, $comment["userId"])) {
            $data             = array();
            $data["id"]       = $comment["id"];
            $data["post_id"]  = $comment["post_id"];
            $data["username"] = $comment["username"];
            $data["name"]     = $comment["name"];
            $data["icon"]     = $comment["icon"];
            $data["content"]  = $comment["content"];
            $data["creation"] = $comment["creation"];
            $data["replies"]  = $comment["replies"];
            array_push($response["comments"], $data);
        }
    }
    echoRespnse(200, $response);
});

$app->put('/post/like/:postId', 'authenticate', function($postId) use ($app)
{
    global $user_id, $username, $profileName;
    $action   = $app->request->params('action');
    $db       = new DbHandler();
    $p        = $db->getPostDetails($postId);
    $response = $db->updateLike($user_id, $postId, $action);
    echoRespnse(200, $response);
    if ($response["error"] == false && $p["user_id"] != $user_id && $action == 1) {
        $s = getPostType($p["type"]);
        addNotification(false, $p["user_id"], $p["id"], $user_id, $username, 1, $p["isShared"] == 1 ? $profileName . " liked the " . $s . " you shared." : $profileName . " liked your " . $s . ".", 0);
    }
});

$app->post('/post/comment/:postId', 'authenticate', function($postId) use ($app)
{
    global $user_id, $username, $profileName;
    $comment  = $app->request->params('comment');
    $db       = new DbHandler();
    $p        = $db->getPostDetails($postId);
    $ph       = $db->getUser($p["user_id"]);
    $isFollowing = $db->isFollowingPost($user_id, $postId);
    $response = $db->addComment($user_id, $postId, $comment);
    echoRespnse(200, $response);
    if ($response["error"] == false) {
        if ($user_id != $p["user_id"]) {
          if (!$isFollowing) {
            $db->followPost($user_id, $postId);
          }
        }
        $s = getPostType($p["type"]) . ".";
        if ($p["user_id"] != $user_id) {
            addNotification(false, $p["user_id"], $p["id"], $user_id, $username, 1, $p["isShared"] == 1 ? $profileName . " commented on your shared " . $s : $profileName . " commented on your " . $s, $response["id"]);
        }
        sendPostFollowingNotifications($user_id, $username, $postId, $profileName . " also commented on " . ($ph["id"] == $user_id ? "their " : $ph["name"] . "'s ") . $s, $response["id"]);
    }
});

$app->post('/comment/reply/:commentId', 'authenticate', function($commentId) use ($app) {
  global $user_id, $username, $profileName;
  $reply  = $app->request->params('reply');
  $postId = $app->request->params('postId');
  $db       = new DbHandler();
  $p        = $db->getPostDetails($postId);
  $c        = $db->getCommentDetails($commentId);
  $ch       = $db->getUser($p["user_id"]);
  $response = $db->addCommentReply($user_id, $commentId, $reply);
  echoRespnse(200, $response);
  if ($response["error"] == false) {
      $s = getPostType($p["type"]) . ".";
      if ($c["user_id"] != $user_id) {
          addNotification(false, $c["user_id"], $postId, $user_id, $username, 4, $profileName . " replied to your comment on " . ($ch["id"] == $user_id ? "their " : ($ch["name"] . "'s ")) . $s, $commentId);
      } else {
          sendCommentNotifications($user_id, $username, $postId, $profileName . " replied to their comment.", $commentId);
      }
  }
});

$app->put('/delete/reply/:commentId', 'authenticate', function($commentId) use ($app)
{
    global $user_id;
    $reply_id = $app->request->params('id');
    $db         = new DbHandler();
    $response   = $db->deleteReply($user_id, $commentId, $reply_id);
    echoRespnse(200, $response);
});

$app->put('/delete/comment/:postId', 'authenticate', function($postId) use ($app)
{
    global $user_id;
    $comment_id = $app->request->params('id');
    $db         = new DbHandler();
    $response   = $db->deleteComment($user_id, $postId, $comment_id);
    echoRespnse(200, $response);
});

$app->put('/delete/post/:postId', 'authenticate', function($postId) use ($app)
{
    global $user_id;
    $db       = new DbHandler();
    $response = $db->deletePost($user_id, $postId);
    echoRespnse(200, $response);
});

// Hashtag System
$app->get('/hashtag/feed/:hashtag', 'authenticate', function($hashtag) use ($app)
{
    global $user_id;
    $db                = new DbHandler();
    $feeds             = $db->retrieveHashtagFeed("#" . $hashtag, $user_id);
    $response["error"] = false;
    $response["feeds"] = array();
    while ($feed = $feeds->fetch(PDO::FETCH_ASSOC)) {
        $data = array();
        if (!$db->isUserBlocked($feed["user_id"], $user_id)) {
            if (isUserClearAudience($user_id, $feed["user_id"], $feed["audience"])) {
                $data = array();

                // User
                $data['user_id']      = $feed["user_id"];
                $data["username"]     = $feed["username"];
                $data["name"]         = $feed["name"];
                $data["profilePhoto"] = $feed["profilePhoto"];
                $data["isVerified"]   = $feed["isVerified"];

                // Post
                $post                 = array();
                $post["postId"]       = $feed["postId"];
                $post["type"]         = $feed["type"];
                $post["content"]      = $feed["content"];
                $post["description"]  = $feed["description"];
                $post["creation"]     = $feed["creation"];
                $post["isLiked"]      = $feed["isLiked"] == NULL ? 0 : $feed["isLiked"];
                $post["likes"]        = $feed["likes"];
                $post["shares"]       = $feed["isShared"] == 1 ? $feed["shared_shares"] : $feed["shares"];
                $post["audience"]     = $feed["audience"] == NULL ? 0 : $feed["audience"];
                $post["comments"]     = $feed["comments"];
                $post["isShared"]     = $feed["isShared"];
                $post["shareId"]      = $feed["shared_id"];
                $post["sharedPostId"] = $feed["shared_post_id"];
                $data["post"]         = $post;

                // Original Poster (if any)
                if ($post["isShared"] == 1) {
                    $poster               = array();
                    $sharedUser           = $db->getUser($post["shareId"]);
                    $poster['user_id']    = $sharedUser["id"];
                    $poster["username"]   = $sharedUser["username"];
                    $poster["name"]       = $sharedUser["name"];
                    $data["profilePhoto"] = $sharedUser["profilePhoto"];
                    $data["isVerified"]   = $sharedUser["isVerified"];
                    $data["sharedUser"]   = $poster;
                }
                array_push($response["feeds"], $data);
            }
        }
    }
    echoRespnse(200, $response);
});

$app->get('/hashtag/:hashtag', 'authenticate', function($hashtag) use ($app)
{
    global $user_id;
    $db                      = new DbHandler();
    $response["isAvailable"] = $db->isHashtag("#" . $hashtag, $user_id);
    $response["hashtag"]     = "#" . $hashtag;
    $response["error"]       = false;
    echoRespnse(200, $response);
});

function sendCommentNotifications($user_id, $username, $postId, $messageData, $commentId) {
  $db        = new DbHandler();
  $users = $db->getCommentFollowings($commentId);
  addNotification(true, $users, $postId, $user_id, $username, 4, $messageData, $commentId);
}

function sendFollowersNotifications($user_id, $username, $postId, $messageData)
{
    $db        = new DbHandler();
    $followers = $db->retrieveFollowers($user_id);
    addNotification(true, $followers, $postId, $user_id, $username, 1, $messageData, 0);
}

function sendPostFollowingNotifications($user_id, $username, $postId, $notification, $commentId)
{
    $db         = new DbHandler();
    $followings = $db->getPostFollowings($postId);
    addNotification(true, $followings, $postId, $user_id, $username, 1, $notification, $commentId);
}

// Block System
$app->put('/user/block/:id', 'authenticate', function($id) use ($app)
{
    global $user_id;
    $db = new DbHandler();
    if (!$db->isUserBlocked($id, $user_id)) {
        $response = $db->blockUser($id, $user_id);
    }
    echoRespnse(200, $response);
});

// Post Sharing Requests
$app->post('/post/share/:postId', 'authenticate', function($postId) use ($app)
{
    global $user_id, $username, $profileName;
    $response      = array();
    $shareAudience = $app->request->params('audience');
    $db            = new DbHandler();
    $p             = $db->getPostDetails($postId);
    if ($p != NULL) {
        if ($p["audience"] == 2) {
            if ($shareAudience == 2 || $shareAudience == 1) {
                $response = $db->sharePost($user_id, $p["type"], $shareAudience, $p["description"], $p["content"], $p["id"], $p["user_id"]);
                echoRespnse(200, $response);
                if ($response["error"] == false) {
                    addNotification(false, $p["user_id"], $p["id"], $user_id, $username, 1, $profileName . " shared your post.", 0);
                    sendFollowersNotifications($user_id, $username, $postId, $profileName . " shared a post.");
                }
            } else {
                $response["error"] = true;
                echoRespnse(200, $response);
            }
        } else {
            $response["error"] = true;
            echoRespnse(200, $response);
        }
    } else {
        $response["error"] = true;
        echoRespnse(200, $response);
    }
});

// Audience System
function isUserClearAudience($user_id, $id, $audience)
{
    $db = new DbHandler();
    if ($user_id != $id) {
        $relation = $db->getRelation($user_id, $id);
        if ($audience == 2) {
            return true;
        } else if ($audience == 1) {
            if ($relation["isFriend"] == 2 || $relation["isFollowing"] == 1) {
                return true;
            }
        } else if ($audience == 0) {
            if ($relation["isFriend"] == 2) {
                return true;
            }
        }
    } else {
        return true;
    }
}

// Follower System
$app->post('/user/follow/:user', 'authenticate', function($follow_user) use ($app)
{
    global $user_id, $username, $profileName;
    $db          = new DbHandler();
    $follow_user = $db->getUserByUsername($follow_user);
    $follow_user = $follow_user["id"];
    $relation    = $db->getRelation($user_id, $follow_user);
    $response    = array();
    if ($relation["isFollowing"] != 1) {
        $response = $db->followUser($user_id, $follow_user);
        if ($response["error"] == false) {
            addNotification(false, $follow_user, 0, $user_id, $username, 1, $profileName . " is following you.", 0);
        }
    } else {
        $response["error"] = false;
    }
    echoRespnse(200, $response);
});

$app->post('/user/unfollow/:user', 'authenticate', function($follow_user) use ($app)
{
    global $user_id, $username, $profileName;
    $db          = new DbHandler();
    $follow_user = $db->getUserByUsername($follow_user);
    $follow_user = $follow_user["id"];
    $relation    = $db->getRelation($user_id, $follow_user);
    $response    = array();
    if ($relation["isFollowing"] == 1) {
        $response = $db->unfollowUser($user_id, $follow_user);
    } else {
        $response["error"] = false;
    }
    echoRespnse(200, $response);
});

// Messaging System
$app->post('/user/message/:id', 'authenticate', function($to_user_id) use ($app)
{
    global $user_id;
    $db = new DbHandler();
    verifyRequiredParams(array(
        'message'
    ));
    $from_user_id = $user_id;
    $receiver     = $db->getUserByUsername($to_user_id);
    if (!$db->isUserBlocked($user_id, $receiver["id"])) {
        $message_type = $app->request->params('msg_type');
        $message      = $app->request->params('message');
        $response     = addMessage($user_id, $receiver["id"], $message_type, $message);
    } else {
        $response["error"] = true;
    }
    echoRespnse(200, $response);
});

$app->post('/user/message_read/:id', 'authenticate', function($id) use ($app)
{
    global $user_id;
    $db       = new DbHandler();
    $response = $db->markMessageAsRead($user_id, $id);
    echoRespnse(200, $response);
});

// Friend Request System
$app->post('/user/remove_friend/:id', 'authenticate', function($friend_id) use ($app)
{
    global $user_id;
    $response = array();
    $db       = new DbHandler();
    $response = $db->removeAsFriend($user_id, $friend_id);
    echoRespnse(200, $response);
});

$app->post('/user/add_friend/:id', 'authenticate', function($friend_id) use ($app)
{
    global $user_id, $username, $profileName;
    $response = array();
    $db       = new DbHandler();
    if (!$db->isUserBlocked($user_id, $friend_id)) {
        $response = $db->addAsFriend($user_id, $friend_id);
        if ($response["error"] == false) {
            addNotification(false, $friend_id, 0, $user_id, $username, 2, $profileName . " sent you a friend request.", 0);
        }
    } else {
        $response["error"] = true;
    }
    echoRespnse(200, $response);
});

$app->post('/user/confirm_friend/:id', 'authenticate', function($friend_id) use ($app)
{
    global $user_id, $username, $profileName;
    $response = array();
    $db       = new DbHandler();
    $relation = $db->getRelation($user_id, $friend_id);
    if ($relation["isFriend"] == 1 && $relation["action"] == $user_id) {
        $response = $db->confirmFriend($user_id, $friend_id);
        if ($response["error"] == false) {
            addNotification(false, $friend_id, 0, $user_id, $username, 1, $profileName . " accepted your friend request.", 0);
        }
    } else {
        $response["error"] = true;
    }
    echoRespnse(200, $response);
});

$app->get('/user/request_list/:from', 'authenticate', function($from) use ($app)
{
    global $user_id;
    $response             = array();
    $db                   = new DbHandler();
    $request_list         = $db->retrieveFriendList($user_id);
    $response["error"]    = false;
    $response["requests"] = array();
    while ($request = $request_list->fetch(PDO::FETCH_ASSOC)) {
        $r                  = $request["user_id"];
        $r                  = $db->getUser($r);
        $data               = array();
        $data["id"]         = $r["id"];
        $data["name"]       = $r["name"];
        $data["username"]   = $r["username"];
        $data["icon"]       = $r["profilePhoto"];
        $data["creation"]   = $request["creation"];
        $data["location"]   = $r["location"] == null ? "" : $r["location"];
        $data["isVerified"] = $r["isVerified"];
        array_push($response["requests"], $data);
    }
    echoRespnse(200, $response);
});

$app->post('/user/notification/:id', 'authenticate', function($id) use ($app)
{
    global $user_id;
    $db       = new DbHandler();
    $response = $db->markNotificationAsRead($user_id, $id);
    echoRespnse(200, $response);
});

function updateNotification($user_id)
{
    return getNotifications($user_id);
}

function updateMessages($user_id)
{
    return getAllMessages($user_id);
}

function getPostType($type)
{
    if ($type == 1) {
        return "photo";
    } else if ($type == 2) {
        return "video";
    } else {
        return "post";
    }
}

function addMessage($from_user_id, $to_user_id, $message_type, $message)
{
    require_once __DIR__ . '/libs/gcm/gcm.php';
    require_once __DIR__ . '/libs/gcm/push.php';
    $db       = new DbHandler();
    $gcm      = new GCM();
    $push     = new Push();
    $response = array();
    $result   = $db->addMessage($from_user_id, $to_user_id, $message_type, $message);
    if ($result["error"] == false) {
        $s                = $db->getUser($from_user_id);
        $receiver         = $db->getUser($to_user_id);
        $data             = array();
        $data['user']     = $receiver["username"];
        $data['id']       = $result["message_id"];
        $data['type']     = $message_type;
        $data['message']  = $message;
        $data['creation'] = $result["creation"];
        $data['action']   = PUSH_TYPE_MESSAGE;

        // Sender node
        $sender             = array();
        $sender["id"]       = $s["id"];
        $sender["username"] = $s["username"];
        $sender["name"]     = $s["name"];
        $sender["email"]    = $s["email"];
        $sender["icon"]     = $s["profilePhoto"];
        $data['sender']     = $sender;

        $push->setId($data["id"]);
        $push->setTitle(TITLE);
        $push->setType(PUSH_TYPE_MESSAGE);
        $push->setData($data);
        $push->setIsBackground(false);
        $gcm->send($receiver['registration_id'], $push->getPush());
        $response["error"] = false;
    } else {
        $response["error"] = true;
    }
    echoRespnse(200, $response);
}

function getAllMessages($user_id)
{
    require_once __DIR__ . '/libs/gcm/gcm.php';
    require_once __DIR__ . '/libs/gcm/push.php';
    $db           = new DbHandler();
    $gcm          = new GCM();
    $push         = new Push();
    $messages     = $db->getAllMessages($user_id);
    $isBackground = $messages->rowCount() > 1 ? TRUE : FALSE;
    while ($message = $messages->fetch(PDO::FETCH_ASSOC)) {
        $receiver         = $db->getUser($user_id);
        $s                = $db->getUser($message["sender"]);
        $data             = array();
        $data['user']     = $receiver["username"];
        $data['id']       = $message["id"];
        $data['type']     = $message["type"];
        $data['message']  = $message["message"];
        $data['creation'] = $message["creation"];
        $data['action']   = PUSH_TYPE_MESSAGE;

        // Sender node
        $sender             = array();
        $sender["id"]       = $s["id"];
        $sender["username"] = $s["username"];
        $sender["name"]     = $s["name"];
        $sender["email"]    = $s["email"];
        $sender["icon"]     = $s["profilePhoto"];
        $data['sender']     = $sender;

        $push->setId($data["id"]);
        $push->setTitle(TITLE);
        $push->setType(PUSH_TYPE_MESSAGE);
        $push->setData($data);
        $push->setIsBackground($isBackground);
        $gcm->send($receiver['registration_id'], $push->getPush());
    }
    if ($isBackground) {
        $data["messageData"] = "You've " . $messages->rowCount() . " new messages.";
        $push->setId(0);
        $push->setTitle(TITLE);
        $push->setType(PUSH_TYPE_MESSAGE);
        $push->setData($data);
        $push->setIsBackground(FALSE);
        $push->setIsCustom(TRUE);
        $gcm->send($receiver['registration_id'], $push->getPush());
    }
    return REQUEST_PASSED;
}

function getNotifications($user_id)
{
    require_once __DIR__ . '/libs/gcm/gcm.php';
    require_once __DIR__ . '/libs/gcm/push.php';
    $db            = new DbHandler();
    $gcm           = new GCM();
    $push          = new Push();
    $notifications = $db->getNotifications($user_id);
    $isBackground  = $notifications->rowCount() > 1 ? TRUE : FALSE;
    $receiver      = $db->getUser($user_id);
    while ($notification = $notifications->fetch(PDO::FETCH_ASSOC)) {
        $sender              = $db->getUser($notification["userId"]);
        $data                = array();
        $data['user']        = $notification["user"];
        $data["commentId"]   = $notification["commentId"];
        $data['postId']      = $notification["postId"];
        $data['userId']      = $notification["userId"];
        $data['username']    = $notification["username"];
        $data['icon']        = $sender["profilePhoto"];
        $data['action']      = $notification["action"];
        $data['messageData'] = $notification["messageData"];
        $data['creation']    = $notification["creation"];
        $push->setId($notification["id"]);
        $push->setTitle(TITLE);
        $push->setType($data['action']);
        $push->setData($data);
        $push->setIsBackground($isBackground);
        $gcm->send($receiver['registration_id'], $push->getPush());
    }
    if ($isBackground) {
        $data["messageData"] = "You've " . $notifications->rowCount() . " new notifications.";
        $push->setId(0);
        $push->setTitle(TITLE);
        $push->setType(PUSH_TYPE_NOTIFICATION);
        $push->setData($data);
        $push->setIsBackground(FALSE);
        $push->setIsCustom(TRUE);
        $gcm->send($receiver['registration_id'], $push->getPush());
    }
    return REQUEST_PASSED;
}

function addNotification($isArray, $userId, $postId, $senderId, $username, $action, $messageData, $commentId)
{
    require_once __DIR__ . '/libs/gcm/gcm.php';
    require_once __DIR__ . '/libs/gcm/push.php';
    $db             = new DbHandler();
    $gcm            = new GCM();
    $push           = new Push();
    $notificationId = rand(1, 100000) . $senderId;
    if ($isArray) {
        $registration_ids = array();
        while ($u = $userId->fetch(PDO::FETCH_ASSOC)) {
            if ($u["userId"] != null) {
                if ($u["userId"] != $senderId) {
                    if (!$db->isUserBlocked($u["userId"], $senderId)) {
                        $result              = $db->addNotification($notificationId, $u["userId"], $postId, $senderId, $username, $action, $messageData, $commentId);
                        $sender              = $db->getUser($senderId);
                        $receiver            = $db->getUser($u["userId"]);
                        $data                = array();
                        $data['user']        = $userId;
                        $data["commentId"]   = $commentId;
                        $data['postId']      = $postId;
                        $data['userId']      = $senderId;
                        $data['username']    = $username;
                        $data['icon']        = $sender["profilePhoto"];
                        $data['action']      = $action;
                        $data['messageData'] = $messageData;
                        $data['creation']    = $result["creation"];
                        $push->setId($notificationId);
                        $push->setTitle(TITLE);
                        $push->setType($action);
                        $push->setData($data);
                        $push->setIsBackground(false);
                        array_push($registration_ids, $receiver['registration_id']);
                    }
                }
            }
        }
        $gcm->sendToGroup($registration_ids, $push->getPush());
    } else {
        $result              = $db->addNotification($notificationId, $userId, $postId, $senderId, $username, $action, $messageData, $commentId);
        $sender              = $db->getUser($senderId);
        $receiver            = $db->getUser($userId);
        $data                = array();
        $data['user']        = $userId;
        $data["commentId"]   = $commentId;
        $data['postId']      = $postId;
        $data['userId']      = $senderId;
        $data['username']    = $username;
        $data['icon']        = $sender["profilePhoto"];
        $data['action']      = $action;
        $data['messageData'] = $messageData;
        $data['creation']    = $result["creation"];
        $push->setId($notificationId);
        $push->setTitle(TITLE);
        $push->setType($action);
        $push->setData($data);
        $push->setIsBackground(false);
        $gcm->send($receiver['registration_id'], $push->getPush());
    }
}

/* Verifying required params posted or not */
function verifyRequiredParams($required_fields)
{
    $error          = false;
    $error_fields   = "";
    $request_params = array();
    $request_params = $_REQUEST;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }
    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }

    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response            = array();
        $app                 = \Slim\Slim::getInstance();
        $response["error"]   = true;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoRespnse(400, $response);
        $app->stop();
    }
}

function authenticate(\Slim\Route $route)
{
    $headers  = apache_request_headers();
    $response = array();
    $app      = \Slim\Slim::getInstance();
    if (isset($headers['Authorization'])) {
        $db  = new DbHandler();
        $api = $headers['Authorization'];
        if (!$db->checkApi($api)) {
            $response["error"] = true;
            $response["code"]  = 2;
            echoRespnse(401, $response);
            $app->stop();
        } else {
            global $user_id;
            global $username;
            global $profileName;
            $user = $db->getUserId($api);
            if ($user != NULL) {
                $user_id     = $user["id"];
                $username    = $user["username"];
                $profileName = $user["name"];
                $isDisabled  = $user["isDisabled"];
                if ($isDisabled == 1) {
                    $response["error"]  = true;
                    $response["code"]   = ACCOUNT_DISABLED;
                    $response["reason"] = $user["disableReason"];
                    echoRespnse(200, $response);
                    $app->stop();
                }
            } else {
                $response["error"] = true;
                $response["code"]  = SESSION_EXPIRED;
            }
        }
    } else {
        $response["error"] = true;
        $response["code"]  = SESSION_EXPIRED;
        echoRespnse(400, $response);
        $app->stop();
    }
}

function validateEmail($email)
{
    $app = \Slim\Slim::getInstance();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["error"] = true;
        $response["code"]  = EMAIL_INVALID;
        echoRespnse(200, $response);
        $app->stop();
    }
}

function IsNullOrEmptyString($str)
{
    return (!isset($str) || trim($str) === '');
}

function echoRespnse($status_code, $response)
{
    $app = \Slim\Slim::getInstance();
    $app->status($status_code);
    $app->contentType('application/json');
    echo json_encode($response);
}
$app->run();

?>
