<?php
class DbHandler
{
    private $conn;
    function __construct()
    {
        require_once dirname(__FILE__) . '/db_connect.php';
        $db         = new DbConnect();
        $this->conn = $db->connect();
    }

    public function createUser($name, $username, $email, $password)
    {
        require_once 'PassHash.php';
        date_default_timezone_set('UTC');
        $crDate   = date("Y-m-d H:i:s");
        $response = array();
        if (!$this->isEmailExists($email)) {
          if (!$this->isUserExists($username)) {
              $profilePhoto = PROFILE_HOST . $username;
              $password_hash = PassHash::hash($password);
              $api           = $this->generateApiKey($username);
              $stmt          = $this->conn->prepare("INSERT INTO users (name, username, email, password, api, profilePhoto, created_At) values(:var1, :var2, :var3, :var4, :var5, :profilePhoto, :var6)");
              $stmt->bindParam(":var1", $name);
              $stmt->bindParam(":var2", $username);
              $stmt->bindParam(":var3", $email);
              $stmt->bindParam(":var4", $password_hash);
              $stmt->bindParam(":var5", $api);
              $stmt->bindParam(":profilePhoto", $profilePhoto);
              $stmt->bindParam(":var6", $crDate);
              $result = $stmt->execute();
              $stmt   = null;
              if ($result) {
                  $response["error"]   = false;
                  $response["api"]     = $this->getApi($username);
                  $response["account"] = $this->getUserByEmail($email);
              } else {
                  $response["error"]   = true;
                  $response["message"] = UNKNOWN_ERROR;
              }
          } else {
              $response["error"] = true;
              $response["code"]  = USER_ALREADY_EXISTS;
          }
        } else {
          $response["error"] = true;
          $response["code"]  = EMAIL_ALREADY_EXISTS;
        }
        return $response;
    }

    public function userLogin($username, $password)
    {
        require_once 'PassHash.php';
        $response = array();
        $stmt     = $this->conn->prepare("SELECT username, password, isDisabled, disableReason FROM users WHERE username = :var1 OR email = :var1");
        $stmt->bindParam(":var1", $username);
        $stmt->execute();
        $acc           = $stmt->fetch();
        $u = $acc["username"];
        $password_hash = $acc["password"];
        $isDisabled = $acc["isDisabled"];
        if ($isDisabled != 1) {
          if ($stmt->rowCount() > 0) {
              if (PassHash::check_password($password_hash, $password)) {
                  $response["api"]     = $this->getApi($u);
                  $response['error']   = false;
                  $response['account'] = $this->getUserByUsername($u);
              } else {
                  $response["error"] = true;
                  $response["code"]  = PASSWORD_INCORRECT;
              }
          } else {
              $response["error"] = true;
              $response["code"]  = USER_INVALID;
          }
        } else {
          $response["error"] = true;
          $response["reason"] = $acc["disableReason"];
          $response["code"]  = ACCOUNT_DISABLED;
        }
        return $response;
    }

    public function reportPost($user_id, $postId, $action, $reason) {
        $response = array();
        date_default_timezone_set('UTC');
        $crDate = date("Y-m-d H:i:s");
        $stmt     = $this->conn->prepare("INSERT INTO reports (postId, report_by, action, reason, creation) VALUES (:postId, :reportBy, :action, :reason, :creation);");
        $stmt->bindParam(":postId", $postId);
        $stmt->bindParam(":reportBy", $user_id);
        $stmt->bindParam(":action", $action);
        $stmt->bindParam(":reason", $reason);
        $stmt->bindParam(":creation", $crDate);
        if ($stmt->execute()) {
            $response["error"] = false;
        } else {
            $response["error"] = true;
        }
        return $response;
    }

    public function updateName($user_id, $name) {
      $response = array();
      $stmt     = $this->conn->prepare("UPDATE users SET name = :name WHERE id = :user_id");
      $stmt->bindParam(":user_id", $user_id);
      $stmt->bindParam(":name", $name);
      if ($stmt->execute()) {
          $response["name"] = $name;
      } else {
          $response["error"] = true;
          $response["code"]  = REQUEST_FAILED;
      }
      return $response;
    }

    public function updatePhotoLink($user_id, $link, $profilePhoto) {
      $response = array();
      $stmt     = $this->conn->prepare("UPDATE users SET profileLink = :profileLink, profilePhoto = :profilePhoto WHERE id = :user_id");
      $stmt->bindParam(":user_id", $user_id);
      $stmt->bindParam(":profileLink", $link);
      $stmt->bindParam(":profilePhoto", $profilePhoto);
      if ($stmt->execute()) {
          $response["error"] = false;
          $response["link"] = $link;
          $response["profilePhoto"] = $profilePhoto;
      } else {
          $response["error"] = true;
          $response["code"]  = REQUEST_FAILED;
      }
      return $response;
    }

    public function updateAbout($user_id, $description) {
      $response = array();
      $stmt     = $this->conn->prepare("UPDATE users SET description = :about WHERE id = :user_id");
      $stmt->bindParam(":user_id", $user_id);
      $stmt->bindParam(":about", $description);
      if ($stmt->execute()) {
          $response["description"] = $description;
      } else {
          $response["error"] = true;
          $response["code"]  = REQUEST_FAILED;
      }
      return $response;
    }

    public function updateRelationship($user_id, $relationship) {
      $response = array();
      $stmt     = $this->conn->prepare("UPDATE users SET relationship = :relationship WHERE id = :user_id");
      $stmt->bindParam(":user_id", $user_id);
      $stmt->bindParam(":relationship", $relationship);
      if ($stmt->execute()) {
          $response["relationship"] = $relationship;
      } else {
          $response["error"] = true;
          $response["code"]  = REQUEST_FAILED;
      }
      return $response;
    }

    public function updateGender($user_id, $gender) {
      $response = array();
      $stmt     = $this->conn->prepare("UPDATE users SET gender = :gender WHERE id = :user_id");
      $stmt->bindParam(":user_id", $user_id);
      $stmt->bindParam(":gender", $gender);
      if ($stmt->execute()) {
          $response["gender"] = $gender;
      } else {
          $response["error"] = true;
          $response["code"]  = REQUEST_FAILED;
      }
      return $response;
    }

    public function updateLocation($user_id, $location) {
      $response = array();
      $stmt     = $this->conn->prepare("UPDATE users SET location = :location WHERE id = :user_id");
      $stmt->bindParam(":user_id", $user_id);
      $stmt->bindParam(":location", $location);
      if ($stmt->execute()) {
          $response["location"] = $location;
      } else {
          $response["error"] = true;
          $response["code"]  = REQUEST_FAILED;
      }
      return $response;
    }

    public function updateLocationPoints($user_id, $longitude, $latitude) {
      $response = array();
      $stmt     = $this->conn->prepare("UPDATE users SET longitude = :longitude, latitude = :latitude WHERE id = :user_id");
      $stmt->bindParam(":user_id", $user_id);
      $stmt->bindParam(":longitude", $longitude);
      $stmt->bindParam(":latitude", $latitude);
      if ($stmt->execute()) {
          $response["longitude"] = $longitude;
          $response["latitude"] = $latitude;
      } else {
          $response["error"] = true;
          $response["code"]  = REQUEST_FAILED;
      }
      return $response;
    }

    public function updatePassword($user_id, $password) {
      require_once 'PassHash.php';
      $password_hash = PassHash::hash($password);
      $response = array();
      $stmt     = $this->conn->prepare("UPDATE users SET password = :password WHERE id = :user_id");
      $stmt->bindParam(":user_id", $user_id);
      $stmt->bindParam(":password", $password_hash);
      if ($stmt->execute()) {
          $response["password"] = true;
      } else {
          $response["error"] = true;
          $response["code"]  = REQUEST_FAILED;
      }
      return $response;
    }

    public function updateVersion($user_id, $version) {
      $response = array();
      $stmt     = $this->conn->prepare("UPDATE users SET appVersion = :version WHERE id = :user_id");
      $stmt->bindParam(":user_id", $user_id);
      $stmt->bindParam(":version", $version);
      if ($stmt->execute()) {
          $response["error"] = false;
          $response["code"]  = REQUEST_PASSED;
      } else {
          $response["error"] = true;
          $response["code"]  = REQUEST_FAILED;
      }
      return $response;
    }

    public function updateGCM($user_id, $id) {
      $response = array();
      $stmt     = $this->conn->prepare("UPDATE users SET registration_id = :gcmId WHERE id = :user_id");
      $stmt->bindParam(":user_id", $user_id);
      $stmt->bindParam(":gcmId", $id);
      if ($stmt->execute()) {
          $response["error"] = false;
          $response["code"]  = REQUEST_PASSED;
      } else {
          $response["error"] = true;
          $response["code"]  = REQUEST_FAILED;
      }
      return $response;
    }

    public function removeBlock($blocked_user, $blocked_by)
    {
        $response = array();
        $stmt     = $this->conn->prepare("DELETE FROM blocked WHERE blocked_by = :blocked_by AND blocked_user = :blocked_user;");
        $stmt->bindParam(":blocked_user", $blocked_user);
        $stmt->bindParam(":blocked_by", $blocked_by);
        if ($stmt->execute()) {
            $response["error"] = false;
        } else {
            $response["error"] = true;
        }
        return $response;
    }

    public function getBlockList($user_id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM blocked WHERE blocked_by = :blocked_by;");
        $stmt->bindParam(":blocked_by", $user_id);
        $stmt->execute();
        $blocked_users = $stmt;
        $stmt          = null;
        return $blocked_users;
    }

    private function generateApiKey($username)
    {
        $u = md5(uniqid(rand(), true));
        $u .= $username;
        return $u;
    }

    public function checkApi($api)
    {
        $stmt = $this->conn->prepare("SELECT id from users WHERE api = :var1");
        $stmt->bindParam(":var1", $api);
        $stmt->execute();
        $num_rows = $stmt->rowCount();
        $stmt     = null;
        return $num_rows > 0;
    }

    public function getImageUri($username)
    {
        $stmt = $this->conn->prepare("SELECT profileLink FROM users WHERE username = :var1");
        $stmt->bindParam(":var1", $username);
        if ($stmt->execute()) {
            $user  = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = null;
            return $user["profileLink"];
        } else {
            return NULL;
        }
    }

    public function getApi($user_id)
    {
        $stmt = $this->conn->prepare("SELECT api FROM users WHERE id = :var1 OR username = :var1");
        $stmt->bindParam(":var1", $user_id);
        if ($stmt->execute()) {
            $api  = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = null;
            return $api["api"];
        } else {
            return NULL;
        }
    }

    public function getUserId($api)
    {
        $stmt = $this->conn->prepare("SELECT id, username, name, isDisabled, disableReason FROM users WHERE api = :var1");
        $stmt->bindParam(":var1", $api);
        if ($stmt->execute()) {
            $acc     = $stmt->fetch();
            $stmt    = null;
            return $acc;
        } else {
            return NULL;
        }
    }

    function isEmailExists($email) {
      $stmt = $this->conn->prepare("SELECT id from users WHERE email = :var1");
      $stmt->bindParam(":var1", $email);
      $stmt->execute();
      $num_rows = $stmt->rowCount();
      $stmt     = null;
      return $num_rows > 0;
    }

    function isUserExists($username)
    {
        $stmt = $this->conn->prepare("SELECT id from users WHERE username = :var1");
        $stmt->bindParam(":var1", $username);
        $stmt->execute();
        $num_rows = $stmt->rowCount();
        $stmt     = null;
        return $num_rows > 0;
    }

    public function isUserBlocked($blocked_user, $blocked_by)
    {
        $stmt = $this->conn->prepare("SELECT * from blocked WHERE (blocked_by = :var1 AND blocked_user = :var2) OR (blocked_user = :var1 AND blocked_by = :var2);");
        $stmt->bindParam(":var1", $blocked_by);
        $stmt->bindParam(":var2", $blocked_user);
        $stmt->execute();
        $num_rows = $stmt->rowCount();
        $stmt     = null;
        return $num_rows > 0;
    }

    public function getNearby($long, $lat) {
      $stmt = $this->conn->prepare("SELECT id, (3959 * acos( cos( radians(:lat) ) * cos( radians(latitude) ) * cos( radians(longitude) - radians(:long) ) + sin( radians(:lat) ) * sin( radians(latitude)))) AS distance
      FROM users HAVING distance < 25 ORDER BY distance LIMIT 0 , 30;");
      $stmt->bindParam(":long", $long);
      $stmt->bindParam(":lat", $lat);
      $stmt->execute();
      $users = $stmt;
      $stmt  = null;
      return $users;
    }

    public function searchUsers($toFind, $user_id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE id != :user AND (username LIKE '%$toFind%' OR name LIKE '%$toFind%' OR email = :var1) AND isDisabled = 0;");
        $stmt->bindParam(":var1", $toFind);
        $stmt->bindParam(":user", $user_id);
        $stmt->execute();
        $users = $stmt;
        $stmt  = null;
        return $users;
    }

    public function removeAsFriend($user_id, $friend_id) {
      $stmt   = $this->conn->prepare("DELETE FROM friends WHERE (user_id = :userId AND user_with = :userWith) OR (user_id = :userWith AND user_with = :userId);");
      $stmt->bindParam(":userId", $user_id);
      $stmt->bindParam(":userWith", $friend_id);
      if ($stmt->execute()) {
          $response["error"] = false;
      }
      else {
          $response["error"] = true;
          $response["code"]  = REQUEST_FAILED;
      }
      return $response;
    }

    public function confirmFriend($user_id, $friend_id) {
      $stmt   = $this->conn->prepare("UPDATE friends SET status = 2 WHERE user_id = :userId AND :userWith = :userWith;");
      $stmt->bindParam(":userId", $friend_id);
      $stmt->bindParam(":userWith", $user_id);
      if ($stmt->execute()) {
          $response["error"] = false;
      }
      else {
          $response["error"] = true;
          $response["code"]  = REQUEST_FAILED;
      }
      return $response;
    }

    public function retrieveFriendList($user_id) {
      $stmt = $this->conn->prepare("SELECT * from friends f where f.user_with = :id and f.status = 1;");
      $stmt->bindParam(":id", $user_id);
      $stmt->execute();
      $friends = $stmt;
      $stmt      = null;
      return $friends;
    }

    public function isFriend($user_id, $id) {
      $f = $this->getRelation_($id, $user_id);
      $isFriend = ($f["status"] != NULL ? $f["status"] : 0);
      return $isFriend == 2;
    }

    public function getRelation($user_id, $id)
    {
        $i                       = $this->getFollowerRelation_($user_id, $id);
        $s                       = $this->getFollowerRelation_($id, $user_id);
        $f = $this->getRelation_($id, $user_id);
        $response["isFollowed"]  = ($i ? 1 : 0);
        $response["isFollowing"] = ($s ? 1 : 0);
        $response["isFriend"] = ($f["status"] != NULL ? $f["status"] : 0);
        if ($f["status"] == 1) {
          $response["action"] = $f["action_user_id"];
        }
        return $response;
    }

    function getFollowerRelation_($user_id, $id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM followers WHERE follower = :id AND following = :user");
        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":user", $user_id);
        $stmt->execute();
        $num_rows = $stmt->rowCount();
        $stmt     = null;
        return $num_rows > 0;
    }

    function getRelation_($user_id, $id)
    {
      $stmt = $this->conn->prepare("SELECT status, action_user_id FROM friends WHERE user_id = :id AND user_with = :user OR user_id = :user AND user_with = :id;");
      $stmt->bindParam(":id", $id);
      $stmt->bindParam(":user", $user_id);
      $stmt->execute();
      $relation = $stmt->fetch();
      return $relation;
    }

    public function markNotificationAsRead($user_id, $notification_id) {
      $stmt = $this->conn->prepare("UPDATE notifications SET isRead = 1 WHERE id = :id AND user = :user_id;");
      $stmt->bindParam(":id", $notification_id);
      $stmt->bindParam(":user_id", $user_id);
      if ($stmt->execute()) {
          $response["error"] = false;
      }
      else {
          $response["error"] = true;
          $response["code"]  = REQUEST_FAILED;
      }
      return $response;
    }

    public function markMessageAsRead($user_id, $message_id) {
      $stmt = $this->conn->prepare("UPDATE messages SET isSent = 1 WHERE id = :id AND receiver = :user_id;");
      $stmt->bindParam(":id", $message_id);
      $stmt->bindParam(":user_id", $user_id);
      if ($stmt->execute()) {
          $response["error"] = false;
      }
      else {
          $response["error"] = true;
          $response["code"]  = REQUEST_FAILED;
      }
      return $response;
    }

    public function followUser($user_id, $follow_user) {
      date_default_timezone_set('UTC');
      $crDate = date("Y-m-d H:i:s");
      $stmt   = $this->conn->prepare("INSERT INTO followers (follower, following, creation) VALUES (:follower, :following, :creation)");
      $stmt->bindParam(":follower", $user_id);
      $stmt->bindParam(":following", $follow_user);
      $stmt->bindParam(":creation", $crDate);
      if ($stmt->execute()) {
          $response["error"] = false;
      }
      else {
          $response["error"] = true;
          $response["code"]  = REQUEST_FAILED;
      }
      return $response;
    }

    public function unfollowUser($user_id, $follow_user) {
      $stmt   = $this->conn->prepare("DELETE FROM followers WHERE follower = :follower AND following = :following;");
      $stmt->bindParam(":follower", $user_id);
      $stmt->bindParam(":following", $follow_user);
      if ($stmt->execute()) {
          $response["error"] = false;
      }
      else {
          $response["error"] = true;
          $response["code"]  = REQUEST_FAILED;
      }
      return $response;
    }

    public function addMessage($from_user_id, $to_user_id, $message_type, $message) {
      date_default_timezone_set('UTC');
      $crDate = date("Y-m-d H:i:s");
      $stmt   = $this->conn->prepare("SELECT addMessage (:var1, :var2, :var3, :var4, :var5) as PM");
      $stmt->bindParam(":var1", $to_user_id);
      $stmt->bindParam(":var2", $from_user_id);
      $stmt->bindParam(":var3", $message_type);
      $stmt->bindParam(":var4", $message);
      $stmt->bindParam(":var5", $crDate);
      $stmt->execute();
      $result = $stmt->fetch();
      if ($stmt->rowCount() > 0) {
          $response["error"]      = false;
          $response["message_id"] = $result["PM"];
          $response["creation"]   = $crDate;
          $response["code"]       = MESSAGE_SENT;
      } else {
          $response["error"] = true;
          $response["code"]  = FAILED_MESSAGE_SEND;
      }
      return $response;
    }

    public function addNotification($notification_id, $userId, $postId, $sender, $username, $action, $messageData, $commentId) {
      date_default_timezone_set('UTC');
      $crDate   = date("Y-m-d H:i:s");
      $response = array();
      $stmt     = $this->conn->prepare("INSERT INTO notifications(id, user, commentId, postId, userId, username, action, messageData, creation, isRead)
      VALUES (:id, :user, :commentId, :postId, :sender, :username, :action, :messageData, :creation, 0);");
      $stmt->bindParam(":id", $notification_id);
      $stmt->bindParam(":user", $userId);
      $stmt->bindParam(":commentId", $commentId);
      $stmt->bindParam(":postId", $postId);
      $stmt->bindParam(":sender", $sender);
      $stmt->bindParam(":username", $username);
      $stmt->bindParam(":action", $action);
      $stmt->bindParam(":messageData", $messageData);
      $stmt->bindParam(":creation", $crDate);
      if ($stmt->execute()) {
          $last_id = $this->conn->lastInsertId();
          $response["creation"] = $crDate;
          $response["last_id"] = $last_id;
          $response["error"] = false;
      }
      else {
          $response["error"] = true;
          $response["code"]  = REQUEST_FAILED;
      }
      return $response;
    }

    public function addAsFriend($user_id, $friend_id) {
      date_default_timezone_set('UTC');
      $crDate   = date("Y-m-d H:i:s");
      $response = array();
      $stmt     = $this->conn->prepare("INSERT INTO friends (user_id, user_with, status, creation, action_user_id) VALUES (:user_id, :user_with, 1, :creation, :action_user_id);");
      $stmt->bindParam(":user_id", $user_id);
      $stmt->bindParam(":user_with", $friend_id);
      $stmt->bindParam(":creation", $crDate);
      $stmt->bindParam(":action_user_id", $friend_id);
      if ($stmt->execute()) {
          $response["error"] = false;
      }
      else {
          $response["error"] = true;
          $response["code"]  = REQUEST_FAILED;
      }
      return $response;
    }

    public function getAllMessages($user_id) {
      $stmt = $this->conn->prepare("SELECT * FROM messages WHERE receiver = :user AND isSent = 0;");
      $stmt->bindParam(":user", $user_id);
      $stmt->execute();
      $messages = $stmt;
      $stmt = null;
      return $messages;
    }

    public function getNotifications($user_id) {
      $stmt = $this->conn->prepare("SELECT * FROM notifications WHERE user = :user AND isRead = 0;");
      $stmt->bindParam(":user", $user_id);
      $stmt->execute();
      $notifications = $stmt;
      $stmt = null;
      return $notifications;
    }

    public function getAccountStatus($user_id) {
      $stmt = $this->conn->prepare("SELECT isDisabled, disableReason FROM users WHERE id = :id");
      $stmt->bindParam(":id", $user_id);
      $stmt->execute();
      $acc           = $stmt->fetch();
      return $acc;
    }

    public function getUser($user_id)
    {
        $stmt = $this->conn->prepare("SELECT id, username, name, email, relationship, gender, registration_id, isVerified, isDisabled, location, profilePhoto, description, created_At, latitude, longitude,
          (SELECT COUNT(*) FROM posts WHERE user_id = u.id) as totalPosts, (SELECT COUNT(*) FROM posts WHERE user_id = u.id AND type = 1) as totalPhotos, (SELECT COUNT(*) FROM posts WHERE user_id = u.id AND type = 2) as totalVideos,
  (SELECT COUNT(*) FROM followers WHERE following = u.id) as totalFollowers, (SELECT COUNT(*) FROM friends WHERE user_id = u.id OR user_with = u.id AND status = 2) as totalFriends FROM users u WHERE id = :var1");
        $stmt->bindParam(":var1", $user_id);
        if ($stmt->execute()) {
            $u                  = $stmt->fetch();
            $user               = array();
            $user["id"]         = $u["id"];
            $user["username"]   = $u["username"];
            $user["name"]       = $u["name"];
            $user["email"]      = $u["email"];
            $user["registration_id"]      = $u["registration_id"];
            $user["relationship"] = $u["relationship"];
            $user["gender"] = $u["gender"];
            $user["isVerified"] = $u["isVerified"];
            $user["isDisabled"] = $u["isDisabled"];
            $user["location"] = $u["location"] == null ? "" : $u["location"];
            $user["profilePhoto"] = $u["profilePhoto"];
            $user["description"] = $u["description"] == null ? "" : $u["description"];
            $user["created_At"] = $u["created_At"];
            $user["totalPosts"] = $u["totalPosts"];
            $user["totalFollowers"]  = $u["totalFollowers"];
            $user["totalFriends"]  = $u["totalFriends"];
            $user["totalPhotos"] = $u["totalPhotos"];
            $user["totalVideos"] = $u["totalVideos"];
            $user["latitude"] = $u["latitude"];
            $user["longitude"] = $u["longitude"];
            $stmt               = null;
            return $user;
        } else {
            return NULL;
        }
    }

    private function getUserByEmail($email)
    {
        $stmt = $this->conn->prepare("SELECT id, username, name, email, relationship, gender, isVerified, isDisabled, location, profilePhoto, description, created_At, (SELECT COUNT(*) FROM posts WHERE user_id = u.id) as totalPosts,
  (SELECT COUNT(*) FROM followers WHERE following = u.id) as totalFollowers, (SELECT COUNT(*) FROM friends WHERE user_id = u.id OR user_with = u.id AND status = 2) as totalFriends FROM users u WHERE email = :var1");
        $stmt->bindParam(":var1", $email);
        if ($stmt->execute()) {
            $u                  = $stmt->fetch();
            $user               = array();
            $user["id"]         = $u["id"];
            $user["username"]   = $u["username"];
            $user["name"]       = $u["name"];
            $user["email"]      = $u["email"];
            $user["relationship"] = $u["relationship"];
            $user["gender"] = $u["gender"];
            $user["isVerified"] = $u["isVerified"];
            $user["isDisabled"] = $u["isDisabled"];
            $user["location"] = $u["location"] == null ? "" : $u["location"];
            $user["profilePhoto"] = $u["profilePhoto"];
            $user["description"] = $u["description"];
            $user["created_At"] = $u["created_At"];
            $user["totalPosts"] = $u["totalPosts"];
            $user["totalFollowers"]  = $u["totalFollowers"];
            $user["totalFriends"]  = $u["totalFriends"];
            $stmt               = null;
            return $user;
        } else {
            return NULL;
        }
    }

    function getUserByUsername($username)
    {
        $stmt = $this->conn->prepare("SELECT id, username, name, email, relationship, gender, isVerified, isDisabled, location, profilePhoto, description, created_At, (SELECT COUNT(*) FROM posts WHERE user_id = u.id) as totalPosts, (SELECT COUNT(*) FROM followers WHERE following = u.id) as totalFollowers, (SELECT COUNT(*) FROM friends WHERE user_id = u.id OR user_with = u.id AND status = 2) as totalFriends, (SELECT COUNT(*) FROM posts WHERE user_id = u.id AND type = 1) as totalPhotos, (SELECT COUNT(*) FROM posts WHERE user_id = u.id AND type = 2) as totalVideos FROM users u WHERE username = :var1");
        $stmt->bindParam(":var1", $username);
        if ($stmt->execute()) {
            $u                  = $stmt->fetch();
            $user               = array();
            $user["id"]         = $u["id"];
            $user["username"]   = $u["username"];
            $user["name"]       = $u["name"];
            $user["email"]      = $u["email"];
            $user["relationship"] = $u["relationship"];
            $user["gender"] = $u["gender"];
            $user["isVerified"] = $u["isVerified"];
            $user["isDisabled"] = $u["isDisabled"];
            $user["location"] = $u["location"] == null ? "" : $u["location"];
            $user["profilePhoto"] = $u["profilePhoto"];
            $user["description"] = $u["description"] == null ? "" : $u["description"];
            $user["created_At"] = $u["created_At"];
            $user["totalPosts"] = $u["totalPosts"];
            $user["totalVideos"] = $u["totalVideos"];
            $user["totalPhotos"] = $u["totalPhotos"];
            $user["totalFollowers"]  = $u["totalFollowers"];
            $user["totalFriends"]  = $u["totalFriends"];
            $stmt               = null;
            return $user;
        } else {
            return NULL;
        }
    }

    public function retrieveReplies($id)
    {
        $stmt = $this->conn->prepare("SELECT c.id, c.comment_id, u.id as userId, u.username, u.isVerified, u.name, u.profilePhoto as icon, c.content, c.creation FROM comment_replies c JOIN users u ON u.id = c.user_id WHERE comment_id = :var1 order by c.creation");
        $stmt->bindParam(":var1", $id);
        $stmt->execute();
        $replies = $stmt;
        $stmt     = null;
        return $replies;
    }

    public function retrieveComments($id)
    {
        $stmt = $this->conn->prepare("SELECT c.id, c.post_id, u.id as userId, u.username, u.isVerified, u.name, u.profilePhoto as icon, c.content, c.creation, (SELECT COUNT(*) FROM comment_replies WHERE comment_id = c.id) as replies FROM comments c JOIN users u ON u.id = c.user_id WHERE post_id = :var1 order by c.creation");
        $stmt->bindParam(":var1", $id);
        $stmt->execute();
        $comments = $stmt;
        $stmt     = null;
        return $comments;
    }

    public function retrieveLikes($user_id, $id)
    {
        $stmt = $this->conn->prepare("SELECT l.post_id, u.id as userId, u.username, u.isVerified, u.name, u.profilePhoto as icon, l.creation FROM likes l JOIN users u ON u.id = l.user_id WHERE post_id = :var1 AND isLiked = 1 order by l.creation desc;");
        $stmt->bindParam(":var1", $id);
        $stmt->execute();
        $likes = $stmt;
        $stmt  = null;
        return $likes;
    }

    public function addPost($user_id, $audience, $content, $description, $type)
    {
        date_default_timezone_set('UTC');
        $crDate   = date("Y-m-d H:i:s");
        $response = array();
        $stmt     = $this->conn->prepare("SELECT addPost(:userId, :audience, :type, :content, :description, :creation) as addPost;");
        $stmt->bindParam(":userId", $user_id);
        $stmt->bindParam(":audience", $audience);
        $stmt->bindParam(":type", $type);
        $stmt->bindParam(":content", $content);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":creation", $crDate);
        if ($stmt->execute()) {
            $stat              = $stmt->fetch();
            $response["id"] = $stat["addPost"];
            $response["error"] = $stat["addPost"] == 0 ? true : false;
            $response["code"]  = REQUEST_PASSED;
        } else {
            $response["error"] = true;
            $response["code"]  = REQUEST_FAILED;
        }
        return $response;
    }

    public function getPost($user_id, $postId) {
      $stmt = $this->conn->prepare("SELECT p.user_id, u.username, u.name, u.isVerified, u.profilePhoto, p.id as postId, p.audience, p.type, p.content, p.description, p.creation, p.shared_post_id, p.isShared, p.shared_id, l.isLiked,
  (SELECT COUNT(*) FROM posts WHERE shared_post_id = p.id) as shares, (SELECT COUNT(*) FROM posts WHERE shared_post_id = p.shared_post_id) as shared_shares, (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND isLiked = 1) as likes, (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments FROM posts p JOIN users u ON u.id = p.user_id
  LEFT JOIN likes l ON l.post_id = p.id AND l.user_id = :var1 WHERE p.id = :postId");
      $stmt->bindParam(":var1", $user_id);
      $stmt->bindParam(":postId", $postId);
      $stmt->execute();
      $feed = $stmt;
      $stmt = null;
      return $feed->fetch(PDO::FETCH_ASSOC);
    }

    public function getCommentDetails($commentId) {
      $stmt = $this->conn->prepare("SELECT * FROM comments WHERE id = :var1");
      $stmt->bindParam(":var1", $commentId);
      if ($stmt->execute()) {
          $p                  = $stmt->fetch();
          $stmt               = null;
          return $p;
      } else {
          return NULL;
      }
    }

    public function getPostDetails($postId) {
      $stmt = $this->conn->prepare("SELECT * FROM posts WHERE id = :var1");
      $stmt->bindParam(":var1", $postId);
      if ($stmt->execute()) {
          $p                  = $stmt->fetch();
          $stmt               = null;
          return $p;
      } else {
          return NULL;
      }
    }

    public function sharePost($user_id, $type, $audience, $description, $content, $share_post_id, $shared_id) {
        date_default_timezone_set('UTC');
        $crDate   = date("Y-m-d H:i:s");
        $response = array();
        $stmt = $this->conn->prepare("SELECT sharePost(:userId, :type, :audience, :description, :content, :share_post_id, :shared_id, :creation) as sharePost;");
        $stmt->bindParam(":userId", $user_id);
        $stmt->bindParam(":type", $type);
        $stmt->bindParam(":audience", $audience);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":content", $content);
        $stmt->bindParam(":share_post_id", $share_post_id);
        $stmt->bindParam(":shared_id", $shared_id);
        $stmt->bindParam(":creation", $crDate);
        if ($stmt->execute()) {
            $stat              = $stmt->fetch();
            $response["error"] = $stat["sharePost"] == 0 ? false : true;
            $response["code"]  = REQUEST_PASSED;
        } else {
            $response["error"] = true;
            $response["code"]  = REQUEST_FAILED;
        }
        return $response;
    }

    public function updateLike($user_id, $post_id, $action)
    {
        date_default_timezone_set('UTC');
        $crDate   = date("Y-m-d H:i:s");
        $response = array();
        $stmt     = $this->conn->prepare("SELECT updateLike(:postId, :userId, :creation, :action) as isLiked;");
        $stmt->bindParam(":postId", $post_id);
        $stmt->bindParam(":userId", $user_id);
        $stmt->bindParam(":action", $action);
        $stmt->bindParam(":creation", $crDate);
        if ($stmt->execute()) {
            $stat              = $stmt->fetch();
            $response["error"] = $stat["isLiked"] == 0 ? false : true;
            $response["code"]  = REQUEST_PASSED;
        } else {
            $response["error"] = true;
            $response["code"]  = REQUEST_FAILED;
        }
        return $response;
    }

    public function addCommentReply($user_id, $comment_id, $comment)
    {
        date_default_timezone_set('UTC');
        $crDate   = date("Y-m-d H:i:s");
        $response = array();
        $stmt     = $this->conn->prepare("SELECT addCommentReply(:commentId, :userId, :comment, :creation) as addCommentReply;");
        $stmt->bindParam(":commentId", $comment_id);
        $stmt->bindParam(":userId", $user_id);
        $stmt->bindParam(":comment", $comment);
        $stmt->bindParam(":creation", $crDate);
        if ($stmt->execute()) {
            $stat              = $stmt->fetch();
            $response["error"] = $stat["addCommentReply"] == 0 ? true : false;
            $response["id"]    = $stat["addCommentReply"];
            $response["code"]  = REQUEST_PASSED;
        } else {
            $response["error"] = true;
            $response["code"]  = REQUEST_FAILED;
        }
        return $response;
    }

    public function addComment($user_id, $post_id, $comment)
    {
        date_default_timezone_set('UTC');
        $crDate   = date("Y-m-d H:i:s");
        $response = array();
        $stmt     = $this->conn->prepare("SELECT addComment(:postId, :userId, :comment, :creation) as addComment;");
        $stmt->bindParam(":postId", $post_id);
        $stmt->bindParam(":userId", $user_id);
        $stmt->bindParam(":comment", $comment);
        $stmt->bindParam(":creation", $crDate);
        if ($stmt->execute()) {
            $stat              = $stmt->fetch();
            $response["error"] = $stat["addComment"] == 0 ? true : false;
            $response["id"]    = $stat["addComment"];
            $response["code"]  = REQUEST_PASSED;
        } else {
            $response["error"] = true;
            $response["code"]  = REQUEST_FAILED;
        }
        return $response;
    }

    public function deletePost($user_id, $post_id) {
      $response = array();
      $stmt = $this->conn->prepare("SELECT deletePost(:userId, :postId) as deletePost;");
      $stmt->bindParam(":userId", $user_id);
      $stmt->bindParam(":postId", $post_id);
      if ($stmt->execute()) {
          $stat              = $stmt->fetch();
          $response["error"] = $stat["deletePost"] == 0 ? false : true;
          $response["code"]  = REQUEST_PASSED;
      } else {
          $response["error"] = true;
          $response["code"]  = REQUEST_FAILED;
      }
      return $response;
    }

    public function deleteReply($user_id, $commentId, $replyId)
    {
        $response = array();
        $stmt     = $this->conn->prepare("SELECT deleteReply(:commentId, :userId, :replyId) as deleteReply;");
        $stmt->bindParam(":commentId", $commentId);
        $stmt->bindParam(":userId", $user_id);
        $stmt->bindParam(":replyId", $replyId);
        if ($stmt->execute()) {
            $stat              = $stmt->fetch();
            $response["error"] = $stat["deleteReply"] == 0 ? false : true;
            $response["code"]  = REQUEST_PASSED;
        } else {
            $response["error"] = true;
            $response["code"]  = REQUEST_FAILED;
        }
        return $response;
    }

    public function deleteComment($user_id, $post_id, $commentId)
    {
        $response = array();
        $stmt     = $this->conn->prepare("SELECT deleteComment(:postId, :userId, :commentId) as deleteComment;");
        $stmt->bindParam(":postId", $post_id);
        $stmt->bindParam(":userId", $user_id);
        $stmt->bindParam(":commentId", $commentId);
        if ($stmt->execute()) {
            $stat              = $stmt->fetch();
            $response["error"] = $stat["deleteComment"] == 0 ? false : true;
            $response["code"]  = REQUEST_PASSED;
        } else {
            $response["error"] = true;
            $response["code"]  = REQUEST_FAILED;
        }
        return $response;
    }

    public function getLikesCount($id) {
      $stmt = $this->conn->prepare("SELECT COUNT(*) as likes FROM likes WHERE post_id = :id AND isLiked = 1;");
      $stmt->bindParam(":id", $id);
      $stmt->execute();
      $totalLikes = $stmt->fetch(PDO::FETCH_ASSOC);
      $stmt = null;
      return $totalLikes["likes"];
    }

    public function retrieveMediaFeed($user_id, $isPhotos, $isVideos) {
      $stmt = $this->conn->prepare("SELECT p.id, p.content, p.audience, p.type FROM posts p WHERE p.user_id = :id AND (type = :ptype OR type = :vtype);");
      $stmt->bindValue(":id", $user_id);
      $stmt->bindValue(":ptype", $isPhotos ? 1 : -1);
      $stmt->bindValue(":vtype", $isVideos ? 2 : -2);
      $stmt->execute();
      $feed = $stmt;
      $stmt = null;
      return $feed;
    }

    public function retrieveTrendFeed($id, $from)
    {
        $stmt = $this->conn->prepare("SELECT p.user_id, u.username, u.name, u.isVerified, u.profilePhoto, p.id as postId, p.audience, p.type, p.content, p.description, p.creation, p.shared_post_id, p.isShared, p.shared_id, l.isLiked,
  (SELECT COUNT(*) FROM posts WHERE shared_post_id = p.id) as shares, (SELECT COUNT(*) FROM posts WHERE shared_post_id = p.shared_post_id) as shared_shares, (SELECT COUNT(*) FROM follow_post WHERE postId = p.id AND userId = :var1) as isPostFollowed, (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND isLiked = 1) as likes, (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments FROM posts p JOIN users u ON u.id = p.user_id
  LEFT JOIN likes l ON l.post_id = p.id AND l.user_id = :var1 HAVING shares > 5 AND likes > 20 order by likes, shares desc LIMIT :var2, :var3;");
        $al   = ($from + 10);
        $stmt->bindParam(":var1", $id);
        $stmt->bindValue(":var2", intval($from), PDO::PARAM_INT);
        $stmt->bindValue(":var3", intval($al), PDO::PARAM_INT);
        $stmt->execute();
        $feed = $stmt;
        $stmt = null;
        return $feed;
    }

    public function retrieveFeed($id, $from)
    {
        $stmt = $this->conn->prepare("SELECT p.user_id, u.username, u.name, u.isVerified, u.profilePhoto, p.id as postId, p.audience, p.type, p.content, p.description, p.creation, p.shared_post_id, p.isShared, p.shared_id, l.isLiked,
  (SELECT COUNT(*) FROM posts WHERE shared_post_id = p.id) as shares, (SELECT COUNT(*) FROM posts WHERE shared_post_id = p.shared_post_id) as shared_shares, (SELECT COUNT(*) FROM follow_post WHERE postId = p.id AND userId = :var1) as isPostFollowed, (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND isLiked = 1) as likes, (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments FROM posts p JOIN users u ON u.id = p.user_id
  LEFT JOIN likes l ON l.post_id = p.id AND l.user_id = :var1 WHERE p.user_id in (select (CASE WHEN user_id = :var1 THEN user_with ELSE user_id END) as f from friends where status = 2 and user_with = :var1 OR user_id = :var1 UNION select following from followers where follower = :var1 group by following) order by p.creation desc LIMIT :var2, :var3;");
        $al   = ($from + 10);
        $stmt->bindParam(":var1", $id);
        $stmt->bindValue(":var2", intval($from), PDO::PARAM_INT);
        $stmt->bindValue(":var3", intval($al), PDO::PARAM_INT);
        $stmt->execute();
        $feed = $stmt;
        $stmt = null;
        return $feed;
    }

    public function retrieveMyFeed($id, $user_id, $from) {
      $stmt = $this->conn->prepare("SELECT p.user_id, u.username, u.name, u.isVerified, u.profilePhoto as icon, p.id as postId, p.type, p.audience, p.isShared, p.shared_id, p.shared_post_id, p.content, p.description, p.creation, l.isLiked,
         (SELECT COUNT(*) FROM posts WHERE shared_post_id = p.id) as shares, (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments from posts p JOIN users u ON u.id = p.user_id LEFT JOIN likes l ON l.post_id = p.id
        AND l.user_id = :user WHERE p.user_id = :var1 order by p.creation desc LIMIT :var2, :var3;");
      $al   = ($from + 10);
      $stmt->bindParam(":var1", $id);
      $stmt->bindParam(":user", $user_id);
      $stmt->bindValue(":var2", intval($from), PDO::PARAM_INT);
      $stmt->bindValue(":var3", intval($al), PDO::PARAM_INT);
      $stmt->execute();
      $feed = $stmt;
      $stmt = null;
      return $feed;
    }

    public function retrieveFriends($id) {
      $stmt = $this->conn->prepare("SELECT * from friends f where (f.user_id = :id OR f.user_with = :id) and f.status = 2;");
      $stmt->bindParam(":id", $id);
      $stmt->execute();
      $friends = $stmt;
      $stmt      = null;
      return $friends;
    }

    public function retrieveFollowers($id) {
      $stmt = $this->conn->prepare("SELECT f.id, u.id as userId, u.name, u.username, u.profilePhoto, f.creation, u.isVerified, u.location FROM followers f LEFT JOIN users u ON u.id = f.follower WHERE f.following = :user_id");
      $stmt->bindParam(":user_id", $id);
      $stmt->execute();
      $followers = $stmt;
      $stmt      = null;
      return $followers;
    }

    public function retrieveFollowings($id) {
      $stmt = $this->conn->prepare("SELECT f.id, u.id as userId, u.name, u.username, u.profilePhoto, f.creation, u.isVerified, u.location FROM followers f LEFT JOIN users u ON u.id = f.following WHERE f.follower = :follower");
      $stmt->bindParam(":follower", $id);
      $stmt->execute();
      $following = $stmt;
      $stmt      = null;
      return $following;
    }

    public function blockUser($blocked_user, $blocked_by)
    {
        $response = array();
        date_default_timezone_set('UTC');
        $crDate = date("Y-m-d H:i:s");
        $stmt   = $this->conn->prepare("INSERT INTO blocked (blocked_user, blocked_by, creation) VALUES (:blocked_user, :blocked_by, :creation);");
        $stmt->bindParam(":blocked_user", $blocked_user);
        $stmt->bindParam(":blocked_by", $blocked_by);
        $stmt->bindParam(":creation", $crDate);
        if ($stmt->execute()) {
            $response["error"] = false;
        } else {
            $response["error"] = true;
        }
        return $response;
    }

    public function retrieveHashtagFeed($hashtag, $user_id)
    {
      $stmt = $this->conn->prepare("SELECT p.user_id, u.username, u.name, u.isVerified, u.profilePhoto, p.id as postId, p.type, p.audience, p.isShared, p.shared_id, p.shared_post_id,
        p.content, p.description, p.creation, l.isLiked, (SELECT COUNT(*) FROM posts WHERE shared_post_id = p.id) as shares, (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND isLiked = 1) as likes, (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments from posts p JOIN users u ON u.id = p.user_id LEFT JOIN likes l ON l.post_id = p.id
        AND l.user_id = :user_id WHERE p.description LIKE '%$hashtag%' AND p.isShared = 0 order by p.creation desc;");
      $stmt->bindParam(":user_id", $user_id);
      $stmt->execute();
      $feed = $stmt;
      $stmt = null;
      return $feed;
    }

    public function isHashtag($hashtag, $user_id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM posts p WHERE description LIKE '%$hashtag%' AND (SELECT COUNT(*) from blocked b
        WHERE blocked_by = :var1 AND blocked_user = p.user_id OR b.blocked_user = :var1 AND b.blocked_by = p.user_id) = 0;");
        $stmt->bindParam(":var1", $user_id);
        $stmt->execute();
        $num_rows = $stmt->rowCount();
        $stmt     = null;
        return $num_rows > 0;
    }

    public function getCommentFollowings($commentId) {
      $stmt = $this->conn->prepare("SELECT DISTINCT (user_id) as userId from comment_replies where comment_id = :commentId;");
      $stmt->bindParam(":commentId", $commentId);
      $stmt->execute();
      $followings = $stmt;
      $stmt     = null;
      return $followings;
    }

    public function getPostFollowings($postId)
    {
        $stmt = $this->conn->prepare("SELECT * FROM follow_post WHERE postId = :postId");
        $stmt->bindParam(":postId", $postId);
        $stmt->execute();
        $followings = $stmt;
        $stmt     = null;
        return $followings;
    }

    public function isFollowingPost($user_id, $postId) {
      $stmt = $this->conn->prepare("SELECT * from follow_post WHERE postId = :postId AND userId = :userId;");
      $stmt->bindParam(":postId", $postId);
      $stmt->bindParam(":userId", $user_id);
      $stmt->execute();
      $num_rows = $stmt->rowCount();
      $stmt     = null;
      return $num_rows > 0;
    }

    public function followPost($user_id, $postId) {
      $response = array();
      $stmt   = $this->conn->prepare("INSERT INTO follow_post (postId, userId) VALUES (:postId, :userId);");
      $stmt->bindParam(":postId", $postId);
      $stmt->bindParam(":userId", $user_id);
      if ($stmt->execute()) {
          $response["error"] = false;
      } else {
          $response["error"] = true;
      }
      return $response;
    }

    public function deleteAccount($user_id)
    {
        $response = array();
        $stmt     = $this->conn->prepare("SELECT deleteAccount(:userId) as deleteAccount;");
        $stmt->bindParam(":userId", $user_id);
        if ($stmt->execute()) {
            $stat              = $stmt->fetch();
            $response["error"] = $stat["deleteAccount"] == 0 ? false : true;
            $response["code"]  = REQUEST_PASSED;
        } else {
            $response["error"] = true;
            $response["code"]  = REQUEST_FAILED;
        }
        return $response;
    }
}
