# MyON
MyON is a PHP ORM.

## Example
This example shows all posts by a user with `id = 4`
```php
<?php

namespace Xesau\MyONTest;

use Xesau\MyON;

include 'vendor/Autoload.php';

$pdo = new PDO('mysql:host=127.0.0.1;dbname=test;charset=UTF-8', 'user', 'password');
MyON::init($pdo);

class User {
  use MyON\DbObject;
  
  public static function objectInfo() {
    return new MyON\ObjectInfo('user', 'id');
  }
  
  public function getPosts() {
    return Post::select()
    ->where('author', '=', $this);
  }

}

class Post {
  use MyON\DbObject;
  
  public static function objectInfo() {
    return (new MyON\ObjectInfo('post', 'id'))
    ->ref('author', '~User')
    ->ref('parent', '~Post');
  }

}

// Find the user
$user = User::byPrim(4);

// Get the posts of this user and sort them so the newest posts are shown first
$posts = $user->getPosts()->desc('time_posted');

// Print a list of the posts of this user in the following format:
// >> #[id]: [message] (on [date])
echo '<h3>Posts by '. $user->username .'</h3>';
echo '<ul>';
foreach($posts as $post) {
  echo '<li>#'. $post->id .': '. $post->message .' ('. date('d-m-Y H:i', $post->time_posted) .'</li>';
}
echo '</ul>';
```
