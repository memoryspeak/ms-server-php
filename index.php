<?php
$db_host = "host";
$db_user = "user";
$db_pass = "pass";
$db_name = "dbname";

header('Content-type: application/json');
$input = json_decode(file_get_contents('php://input'));

$input_method = $input->method ?? '';
$input_username = $input->username ?? '';
$input_md5password = $input->md5password ?? '';
$input_tag = $input->tag ?? [];
$input_title = $input->title ?? '';
$input_body = $input->body ?? '';
$input_files = $input->files ?? [];
$input_search = $input->search ?? [];
$input_file_id = $input->file_id ?? 0;
$input_notes_id = $input->notes_id ?? 0;

try {
  $db_connect = new PDO("mysql:host={$db_host};dbname={$db_name}", $db_user, $db_pass);
  $db_connect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $db_init = $db_connect->prepare(
    "CREATE TABLE IF NOT EXISTS users (".
      "id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,".
      "username TEXT NOT NULL,".
      "md5password TEXT NOT NULL,".
      "UNIQUE (username)".
    ");".
    "CREATE TABLE IF NOT EXISTS notes (".
      "id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,".
      "title TEXT DEFAULT '',".
      "body LONGTEXT DEFAULT '',".
      "datetime TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,".
      "users_id INT NOT NULL,".
      "FOREIGN KEY (users_id) REFERENCES users (id) ON DELETE SET DEFAULT ON UPDATE CASCADE,".
      "UNIQUE (title)".
    ");".
    "CREATE TABLE IF NOT EXISTS tags (".
      "id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,".
      "tag TEXT NOT NULL,".
      "notes_id INT NOT NULL,".
      "users_id INT NOT NULL,".
      "FOREIGN KEY (notes_id) REFERENCES notes (id) ON DELETE SET DEFAULT ON UPDATE CASCADE,".
      "FOREIGN KEY (users_id) REFERENCES users (id) ON DELETE SET DEFAULT ON UPDATE CASCADE".
    ");".
    "CREATE TABLE IF NOT EXISTS files (".
      "id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,".
      "filename TEXT NOT NULL,".
      "size INT NOT NULL,".
      "type TEXT DEFAULT NULL,".
      "extention TEXT DEFAULT NULL,".
      "base64 LONGTEXT NOT NULL,".
      "notes_id INT NOT NULL,".
      "users_id INT NOT NULL,".
      "FOREIGN KEY (notes_id) REFERENCES notes (id) ON DELETE SET DEFAULT ON UPDATE CASCADE,".
      "FOREIGN KEY (users_id) REFERENCES users (id) ON DELETE SET DEFAULT ON UPDATE CASCADE".
    ");".
    "INSERT INTO users (username, md5password) VALUES (:username_0, :md5password_0);"
  );

  $db_init->execute(
    array(
      'username_0' => "username_0",
      'md5password_0' => "password_0"
    )
  );
  $db_init_fetchAll = $db_init->fetchAll(PDO::FETCH_ASSOC);
  $db_init->closeCursor();
  
  $db_get_user_id = $db_connect-> prepare(
    "SELECT * FROM users WHERE username = :username AND md5password = :md5password;"
  );
  $db_get_user_id_answer = $db_get_user_id->execute(
    array(
      'username' => $input_username,
      'md5password' => $input_md5password
    )
  );
  $db_get_user_id_fetch = $db_get_user_id->fetch(PDO::FETCH_ASSOC);
  $db_get_user_id-> closeCursor();
  
  $db_get_notes_count = $db_connect-> prepare(
    "SELECT COUNT(id) AS total FROM notes WHERE users_id = :users_id;"
  );
  $db_get_notes_count_answer = $db_get_notes_count->execute(
    array(
      'users_id' => $db_get_user_id_fetch["id"]
    )
  );
  $db_get_notes_count_fetch = $db_get_notes_count->fetch(PDO::FETCH_ASSOC);
  $db_get_notes_count-> closeCursor();

  switch ($input_method) {
  case 'login':
    if ($db_get_user_id_fetch["username"] == $input_username && $db_get_user_id_fetch["md5password"] == $input_md5password) {
      $output = array(
        'method'  => $input_method
      );
    } else {
      $output = array(
        'method'  => $input_method,
        'exception' => 'unknown user'
      );
    };
    break;
  case 'gettag':
    $db_get_tags = $db_connect-> prepare(
      "SELECT id, tag FROM tags WHERE users_id = :users_id;"
    );
    $db_get_tags_answer = $db_get_tags->execute(
      array(
        'users_id' => $db_get_user_id_fetch["id"]
      )
    );
    $db_get_tags_fetch = $db_get_tags->fetchAll(PDO::FETCH_ASSOC);
    $db_get_tags-> closeCursor();
    $output = array(
      'method'  => $input_method,
      'tags' => $db_get_tags_fetch
    );
    break;
  case 'setnote':
    $db_insert_note = $db_connect->prepare(
      "INSERT INTO notes (title, body, users_id) VALUES (:title, :body, :users_id);"
    );
    $db_insert_note_answer = $db_insert_note->execute(
      array(
        'title' => $input_title,
        'body' => $input_body,
        'users_id' => $db_get_user_id_fetch["id"]
      )
    );
    $lastInsertNotesId = $db_connect->lastInsertId();
    $db_insert_note_fetchAll = $db_insert_note->fetchAll(PDO::FETCH_ASSOC);
    $db_insert_note->closeCursor();

    $lastInsertTagsIdArray = [];
    for ($tag_index = 0; $tag_index < count($input_tag); $tag_index++) {
      $db_insert_tag = $db_connect->prepare(
        "INSERT INTO tags (tag, users_id, notes_id) VALUES (:tag, :users_id, :notes_id);"
      );
      $db_insert_tag_answer = $db_insert_tag->execute(
        array(
          'tag' => $input_tag[$tag_index],
          'users_id' => $db_get_user_id_fetch["id"],
          'notes_id' => $lastInsertNotesId
        )
      );
      $lastInsertTagsId = $db_connect->lastInsertId();
      $db_insert_tag_fetchAll = $db_insert_tag->fetchAll(PDO::FETCH_ASSOC);
      $db_insert_tag->closeCursor();
      array_push($lastInsertTagsIdArray, $lastInsertTagsId);
    };

    $lastInsertFilesIdArray = [];
    for ($file_index = 0; $file_index < count($input_files); $file_index++) {
      $db_insert_file = $db_connect->prepare(
        "INSERT INTO files (filename, size, type, extention, base64, users_id, notes_id) VALUES (:filename, :size, :type, :extention, :base64, :users_id, :notes_id);"
      );
      $db_insert_file_answer = $db_insert_file->execute(
        array(
          'filename' => $input_files[$file_index]->filename,
          'size' => $input_files[$file_index]->size,
          'type' => $input_files[$file_index]->type,
          'extention' => $input_files[$file_index]->extention,
          'base64' => $input_files[$file_index]->base64,
          'users_id' => $db_get_user_id_fetch["id"],
          'notes_id' => $lastInsertNotesId
        )
      );
      $lastInsertFilesId = $db_connect->lastInsertId();
      $db_insert_file_fetchAll = $db_insert_file->fetchAll(PDO::FETCH_ASSOC);
      $db_insert_file->closeCursor();
      array_push($lastInsertFilesIdArray, $lastInsertFilesId);
    };
    
    $output = array(
      'method'  => $input_method
      /*,
      'lastinsertnotesid' => $lastInsertNotesId,
      'lastinserttagsidarray' => $lastInsertTagsIdArray,
      'lastinsertfilesidarray' => $lastInsertFilesIdArray,
      'tagscount' => count($input_tag)*/
    );
    break;
  case 'changenote':
    $queryUpdateNote = "UPDATE notes SET title = :title, body = :body WHERE id = :id AND users_id = :users_id;";
    $executeArrayUpdateNote = array('title' => $input_title, 'body' => $input_body, 'id' => $input_notes_id, 'users_id' => $db_get_user_id_fetch["id"]);
    $dbUpdateNote = $db_connect-> prepare($queryUpdateNote);
    $dbUpdateNote->execute($executeArrayUpdateNote);
    $updateCountNotes = $dbUpdateNote->rowCount();
    $dbUpdateNote-> closeCursor();

    $queryDeleteTags = "DELETE FROM tags WHERE notes_id = :id AND users_id = :users_id;";
    $executeArrayDeleteTags = array('id' => $input_notes_id, 'users_id' => $db_get_user_id_fetch["id"]);
    $dbDeleteTags = $db_connect-> prepare($queryDeleteTags);
    $dbDeleteTags->execute($executeArrayDeleteTags);
    $deleteCountTags = $dbDeleteTags->rowCount();
    $dbDeleteTags-> closeCursor();

    $lastInsertTagsIdArray = [];
    for ($tag_index = 0; $tag_index < count($input_tag); $tag_index++) {
      $db_insert_tag = $db_connect->prepare(
        "INSERT INTO tags (tag, users_id, notes_id) VALUES (:tag, :users_id, :notes_id);"
      );
      $db_insert_tag_answer = $db_insert_tag->execute(
        array(
          'tag' => $input_tag[$tag_index],
          'users_id' => $db_get_user_id_fetch["id"],
          'notes_id' => $input_notes_id
        )
      );
      $lastInsertTagsId = $db_connect->lastInsertId();
      $db_insert_tag_fetchAll = $db_insert_tag->fetchAll(PDO::FETCH_ASSOC);
      $db_insert_tag->closeCursor();
      array_push($lastInsertTagsIdArray, $lastInsertTagsId);
    };

    $lastInsertFilesIdArray = [];
    $updateCountFiles = 0;
    for ($file_index = 0; $file_index < count($input_files); $file_index++) {
      if (is_null($input_files[$file_index]->id)) {
        $db_insert_file = $db_connect->prepare("INSERT INTO files (filename, size, type, extention, base64, users_id, notes_id) VALUES (:filename, :size, :type, :extention, :base64, :users_id, :notes_id);");
        $db_insert_file_answer = $db_insert_file->execute(
          array(
            'filename' => $input_files[$file_index]->filename,
            'size' => $input_files[$file_index]->size,
            'type' => $input_files[$file_index]->type,
            'extention' => $input_files[$file_index]->extention,
            'base64' => $input_files[$file_index]->base64,
            'users_id' => $db_get_user_id_fetch["id"],
            'notes_id' => $input_notes_id
          )
        );
        $lastInsertFilesId = $db_connect->lastInsertId();
        $db_insert_file_fetchAll = $db_insert_file->fetchAll(PDO::FETCH_ASSOC);
        $db_insert_file->closeCursor();
        array_push($lastInsertFilesIdArray, $lastInsertFilesId);
      } else {
        $queryUpdateFile = "UPDATE files SET filename = :filename, extention = :extention WHERE id = :id AND users_id = :users_id;";
        $executeArrayUpdateFile = array('filename' => $input_files[$file_index]->filename, 'extention' => $input_files[$file_index]->extention, 'id' => $input_files[$file_index]->id, 'users_id' => $db_get_user_id_fetch["id"]);
        $dbUpdateFile = $db_connect-> prepare($queryUpdateFile);
        $dbUpdateFile->execute($executeArrayUpdateFile);
        $updateCountFiles += $dbUpdateFile->rowCount();
        $dbUpdateFile-> closeCursor();
      };
    };

    $output = array(
      'method'  => $input_method,
      'updateCountNotes' => $updateCountNotes,
      'lastinserttagsidarray' => $lastInsertTagsIdArray,
      'lastinsertfilesidarray' => $lastInsertFilesIdArray,
      'updateCountFiles' => $updateCountFiles
    );
    break;
  case 'getnote':
    $resultArray = [];
    for ($indexOfInputSearch = 0; $indexOfInputSearch < count($input_search); $indexOfInputSearch++) {
      $result = [];
      $queries = $input_search[$indexOfInputSearch]->queries;
      $queriesLength = count($queries);
      $tags = $input_search[$indexOfInputSearch]->tags;
      $tagsLength = count($tags);
      $notes_id = [];
      if ($queries != []) {
        $queryStringTitleAndBodyLike = "SELECT id FROM notes WHERE (users_id = :users_id";
        $queryStringTitleLike = "";
        $queryStringBodyLike = "";
        $queryStringFilenameLike = "SELECT notes_id FROM files WHERE users_id = :users_id";
        $executeArrayTitleAndBodyLike = array('users_id' => $db_get_user_id_fetch["id"]);
        $executeArrayFilenameLike = array('users_id' => $db_get_user_id_fetch["id"]);
        for ($indexOfQueries = 0; $indexOfQueries < $queriesLength; $indexOfQueries++) {
          $queryNameTitleLike = "query_title_like_".$indexOfQueries;
          $queryNameBodyLike = "query_body_like_".$indexOfQueries;
          $queryNameFilenameLike = "query_filename_like_".$indexOfQueries;
          $queryStringTitleLike .= " AND title LIKE :".$queryNameTitleLike;
          $queryStringBodyLike .= " AND body LIKE :".$queryNameBodyLike;
          $queryStringFilenameLike .= " AND filename LIKE :".$queryNameFilenameLike;
          $executeArrayTitleAndBodyLike[$queryNameTitleLike] = '%'.$queries[$indexOfQueries].'%';
          $executeArrayTitleAndBodyLike[$queryNameBodyLike] = '%'.$queries[$indexOfQueries].'%';
          $executeArrayFilenameLike[$queryNameFilenameLike] = '%'.$queries[$indexOfQueries].'%';
        };
        $queryStringTitleAndBodyLike .= $queryStringTitleLike.") OR (users_id = :users_id".$queryStringBodyLike.");";
        $dbGetNotesIds = $db_connect-> prepare($queryStringTitleAndBodyLike);
        $dbGetNotesIds->execute($executeArrayTitleAndBodyLike);
        $dbGetNotesIdsFetch = $dbGetNotesIds->fetchAll(PDO::FETCH_ASSOC);
        $dbGetNotesIds-> closeCursor();

        $queryStringFilenameLike .= ";";
        $dbGetFilesIds = $db_connect-> prepare($queryStringFilenameLike);
        $dbGetFilesIds->execute($executeArrayFilenameLike);
        $dbGetFilesIdsFetch = $dbGetFilesIds->fetchAll(PDO::FETCH_ASSOC);
        $dbGetFilesIds-> closeCursor();
        
        for ($indexOfDbGetNotesIdsFetch = 0; $indexOfDbGetNotesIdsFetch < count($dbGetNotesIdsFetch); $indexOfDbGetNotesIdsFetch++) {
          array_push($notes_id, $dbGetNotesIdsFetch[$indexOfDbGetNotesIdsFetch]["id"]);
        };

        for ($indexOfDbGetFilesIdsFetch = 0; $indexOfDbGetFilesIdsFetch < count($dbGetFilesIdsFetch); $indexOfDbGetFilesIdsFetch++) {
          array_push($notes_id, $dbGetFilesIdsFetch[$indexOfDbGetFilesIdsFetch]["notes_id"]);
        };

        $notes_id = array_unique($notes_id, SORT_REGULAR);

        if ($tags != []) {
          $queryStringTagLike = "SELECT notes_id FROM tags WHERE users_id = :users_id AND (";
          $executeArrayTagLike = array('users_id' => $db_get_user_id_fetch["id"]);
          for ($indexOfTags = 0; $indexOfTags < $tagsLength; $indexOfTags++) {
            if ($indexOfTags != ($tagsLength - 1)) {
              $queryNameTagLike = "query_tag_like_".$indexOfTags;
              $queryStringTagLike .= "tag LIKE :".$queryNameTagLike." OR ";
              $executeArrayTagLike[$queryNameTagLike] = '%'.$tags[$indexOfTags].'%';
            } else {
              $queryNameTagLike = "query_tag_like_".$indexOfTags;
              $queryStringTagLike .= "tag LIKE :".$queryNameTagLike;
              $executeArrayTagLike[$queryNameTagLike] = '%'.$tags[$indexOfTags].'%';
            };
          };
          $queryStringTagLike .= ");";
          $dbGetTagsIds = $db_connect-> prepare($queryStringTagLike);
          $dbGetTagsIds->execute($executeArrayTagLike);
          $dbGetTagsIdsFetch = $dbGetTagsIds->fetchAll(PDO::FETCH_ASSOC);
          $dbGetTagsIds-> closeCursor();

          $notes_id_cross_tags = [];

          for ($indexOfDbGetTagsIdsFetch = 0; $indexOfDbGetTagsIdsFetch < count($dbGetTagsIdsFetch); $indexOfDbGetTagsIdsFetch++) {
            for ($indexOfNotesIdArray = 0; $indexOfNotesIdArray < count($notes_id); $indexOfNotesIdArray++) {
              if ($notes_id[$indexOfNotesIdArray] == $dbGetTagsIdsFetch[$indexOfDbGetTagsIdsFetch]["notes_id"]) {
                array_push($notes_id_cross_tags, $notes_id[$indexOfNotesIdArray]);
              };
            };
          };
          $notes_id_cross_tags = array_unique($notes_id_cross_tags, SORT_REGULAR);
          $notes_id = $notes_id_cross_tags;
        };
      } else {
        $queryStringTagLike = "SELECT notes_id FROM tags WHERE users_id = :users_id AND (";
        $executeArrayTagLike = array('users_id' => $db_get_user_id_fetch["id"]);
        for ($indexOfTags = 0; $indexOfTags < $tagsLength; $indexOfTags++) {
          if ($indexOfTags != ($tagsLength - 1)) {
            $queryNameTagLike = "query_tag_like_".$indexOfTags;
            $queryStringTagLike .= "tag LIKE :".$queryNameTagLike." OR ";
            $executeArrayTagLike[$queryNameTagLike] = '%'.$tags[$indexOfTags].'%';
          } else {
            $queryNameTagLike = "query_tag_like_".$indexOfTags;
            $queryStringTagLike .= "tag LIKE :".$queryNameTagLike;
            $executeArrayTagLike[$queryNameTagLike] = '%'.$tags[$indexOfTags].'%';
          };
        };
        $queryStringTagLike .= ");";
        $dbGetTagsIds = $db_connect-> prepare($queryStringTagLike);
        $dbGetTagsIds->execute($executeArrayTagLike);
        $dbGetTagsIdsFetch = $dbGetTagsIds->fetchAll(PDO::FETCH_ASSOC);
        $dbGetTagsIds-> closeCursor();

        for ($indexOfDbGetTagsIdsFetch = 0; $indexOfDbGetTagsIdsFetch < count($dbGetTagsIdsFetch); $indexOfDbGetTagsIdsFetch++) {
          array_push($notes_id, $dbGetTagsIdsFetch[$indexOfDbGetTagsIdsFetch]["notes_id"]);
        };
        $notes_id = array_unique($notes_id, SORT_REGULAR);
      };
      if ($notes_id != []) {
        for ($note_id = 0; $note_id < count($notes_id); $note_id++) {
          $queryStringByNotesId = "SELECT title, body, datetime FROM notes WHERE id = :id;";
          $executeArrayByNotesId = array('id' => $notes_id[$note_id]);
          $dbGetByNotesId = $db_connect-> prepare($queryStringByNotesId);
          $dbGetByNotesId->execute($executeArrayByNotesId);
          $dbGetByNotesIdFetch = $dbGetByNotesId->fetch(PDO::FETCH_ASSOC);
          $dbGetByNotesId-> closeCursor();

          $queryStringTagsByNotesId = "SELECT tag FROM tags WHERE notes_id = :notes_id;";
          $executeArrayTagsByNotesId = array('notes_id' => $notes_id[$note_id]);
          $dbGetTagsByNotesId = $db_connect-> prepare($queryStringTagsByNotesId);
          $dbGetTagsByNotesId->execute($executeArrayTagsByNotesId);
          $dbGetTagsByNotesIdFetch = $dbGetTagsByNotesId->fetchAll(PDO::FETCH_ASSOC);
          $dbGetTagsByNotesId-> closeCursor();

          $queryStringFilesByNotesId = "SELECT id, filename, size, type, extention FROM files WHERE notes_id = :notes_id;";
          $executeArrayFilesByNotesId = array('notes_id' => $notes_id[$note_id]);
          $dbGetFilesByNotesId = $db_connect-> prepare($queryStringFilesByNotesId);
          $dbGetFilesByNotesId->execute($executeArrayFilesByNotesId);
          $dbGetFilesByNotesIdFetch = $dbGetFilesByNotesId->fetchAll(PDO::FETCH_ASSOC);
          $dbGetFilesByNotesId-> closeCursor();

          array_push($result, array(
            "id" => $notes_id[$note_id],
            "tags" => $dbGetTagsByNotesIdFetch,
            "title" => $dbGetByNotesIdFetch["title"],
            "body" => $dbGetByNotesIdFetch["body"],
            "files" => $dbGetFilesByNotesIdFetch,
            "datetime" => $dbGetByNotesIdFetch["datetime"]
          ));
        };
      };
      $result = array_unique($result, SORT_REGULAR);
      array_push($resultArray, $result);
    };
    $output = array(
      'method'  => $input_method,
      'result' => $resultArray,
      'notescount' => $db_get_notes_count_fetch["total"]
    );
    break;
  case 'getfile':
    $queryBase64 = "SELECT base64 FROM files WHERE id = :id AND notes_id = :notes_id AND users_id = :users_id;";
    $executeArrayBase64 = array('id' => $input_file_id, 'notes_id' => $input_notes_id, 'users_id' => $db_get_user_id_fetch["id"]);
    $dbGetBase64 = $db_connect-> prepare($queryBase64);
    $dbGetBase64->execute($executeArrayBase64);
    $dbGetBase64Fetch = $dbGetBase64->fetch(PDO::FETCH_ASSOC);
    $dbGetBase64-> closeCursor();

    $output = array(
      'method'  => $input_method,
      'base64' => $dbGetBase64Fetch["base64"]
    );
    break;
  case 'getjsonsizefile':
    $queryBase64 = "SELECT base64 FROM files WHERE id = :id AND notes_id = :notes_id AND users_id = :users_id;";
    $executeArrayBase64 = array('id' => $input_file_id, 'notes_id' => $input_notes_id, 'users_id' => $db_get_user_id_fetch["id"]);
    $dbGetBase64 = $db_connect-> prepare($queryBase64);
    $dbGetBase64->execute($executeArrayBase64);
    $dbGetBase64Fetch = $dbGetBase64->fetch(PDO::FETCH_ASSOC);
    $dbGetBase64-> closeCursor();

    $content = array(
      'method'  => "getfile",
      'base64' => $dbGetBase64Fetch["base64"]
    );
    $json_size = mb_strlen(json_encode($content, JSON_NUMERIC_CHECK), '8bit');
    $output = array(
      'method'  => $input_method,
      'json_size' => $json_size
    );
    break;
  case 'deletefile':
    $queryDeleteFile = "DELETE FROM files WHERE id = :id AND users_id = :users_id;";
    $executeArrayDeleteFile = array('id' => $input_file_id, 'users_id' => $db_get_user_id_fetch["id"]);
    $dbDeleteFile = $db_connect-> prepare($queryDeleteFile);
    $dbDeleteFile->execute($executeArrayDeleteFile);
    $deleteCount = $dbDeleteFile->rowCount();
    $dbDeleteFile-> closeCursor();

    $output = array(
      'method'  => $input_method,
      'deleteCount' => $deleteCount
    );
    break;
  case 'deletenote':
    $queryDeleteTags = "DELETE FROM tags WHERE notes_id = :id AND users_id = :users_id;";
    $executeArrayDeleteTags = array('id' => $input_notes_id, 'users_id' => $db_get_user_id_fetch["id"]);
    $dbDeleteTags = $db_connect-> prepare($queryDeleteTags);
    $dbDeleteTags->execute($executeArrayDeleteTags);
    $deleteCountTags = $dbDeleteTags->rowCount();
    $dbDeleteTags-> closeCursor();

    $queryDeleteFiles = "DELETE FROM files WHERE notes_id = :id AND users_id = :users_id;";
    $executeArrayDeleteFiles = array('id' => $input_notes_id, 'users_id' => $db_get_user_id_fetch["id"]);
    $dbDeleteFiles = $db_connect-> prepare($queryDeleteFiles);
    $dbDeleteFiles->execute($executeArrayDeleteFiles);
    $deleteCountFiles = $dbDeleteFiles->rowCount();
    $dbDeleteFiles-> closeCursor();

    $queryDeleteNote = "DELETE FROM notes WHERE id = :id AND users_id = :users_id;";
    $executeArrayDeleteNote = array('id' => $input_notes_id, 'users_id' => $db_get_user_id_fetch["id"]);
    $dbDeleteNote = $db_connect-> prepare($queryDeleteNote);
    $dbDeleteNote->execute($executeArrayDeleteNote);
    $deleteCountNotes = $dbDeleteNote->rowCount();
    $dbDeleteNote-> closeCursor();

    $output = array(
      'method'  => $input_method,
      'deleteCountTags' => $deleteCountTags,
      'deleteCountFiles' => $deleteCountFiles,
      'deleteCountNotes' => $deleteCountNotes
    );
    break;
  default:
    $output = array(
      'method'  => $input_method,
      'exception' => 'unknown method'
    );
    break;
  };

} catch(PDOException $exception) {
  $output = array(
    'method'  => $input_method,
    'exception' => $exception->getMessage()
  );
};

echo json_encode($output);

?>
