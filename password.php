<?php 
$hash = '$2y$10$xBL7D/7cZEq/58jKu5yIze5Jt3lYv7WRVLITiChXXX9xC2YggGVRC';
$password = 'Aloro.com@6';

if (password_verify($password, $hash)) {
    echo 'Password is correct!';
} else {
    echo 'Wrong password!';
}

?>