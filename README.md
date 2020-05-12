# todo
PHP CLI - Linux Server - To Do - Lists

## Purpose: To encrypt things left, to do, on a Linux Server.

* Each user, has their own password protected, to do, items! *

apt-get install php7.4-cli php7.4-sqlite3

ln -s todo.php /usr/local/bin/todo

Useage: todo help

Useage: todo add "Stuff left...."

Useage: todo

Creates an SQLite3 file in ~/.todo/todo.db

Requires: php 7.0 or better for Lib-Sodium Crypto