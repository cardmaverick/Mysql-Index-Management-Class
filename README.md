# Mysql-Index-Management-Class

A quick, safe, and dynamic PHP utility class for dropping and rebuilding mysql indexes. Set it and forget it!

What This Class Does
====================

Have you ever developed a database heavy program that requires table insert efficiency? If you have, then you understand how important it can be to temporarily drop your *non-unique* indexes while inserting or updating large amounts of data. Simply dropping a handful can make a huge difference on multi million row tables. The problem however is with the management of this process in code. Not all DBA's are programmers, and even if they are, it's a major pain to have to remember to update your code if your index strategy changes, and in development, it will. This class is dynamic. As long as you do not change the table name, it will automatically figure out your tables current *non-unique* BTREE and FULLTEXT indexes. The class also creates recovery and history logs. Should your application ever crash before it can rebuild the indexes it dropped, the class will auto detect this situation and immediately go into index recovery mode when your class instances are initialized in code the next time you run it.

Requirements
============

1.) MYSQLi connection
2.) Sufficient Mysql user privlidges to create tables, add/drop indexes, and insert/delete table rows. 

Important Notes
===============

This class only manages *non-unique* BTREE and FULLTEXT indexes. A future version will include an option for managing unique keys if you are in a situation where this would be safe to do.

This class has mysqli error reporting turned on. It will stop the script if you encounter one. I kept this on because of the nature of my own work, however, you might want to delete them. Just search for 'or die($this->dbConn->error)' statements.

How to Use
==========

The format for initiazling the class is as follows:

```php
$instanceName = new indexManage('TABLE-NAME', MYSQLI-CONNECTION-OBJECT);
```

Example:

```php
$newReportsIndexes = new indexManage('new-reports', $mysqli);
```
Note: The table name is Case Sensitive! If you get it wrong, the class will echo an error message and hint :)

To check your indexes:

```php
$newReportsIndexes->sayIndexes();
```
The above command will echo out your index details in html format.

To drop your indexes:

```php
$newReportsIndexes->dropIndexes();
```
To rebuild your indexes:

```php
$newReportsIndexes->rebuildIndexes();
```
Crash Recovery - Keeping Your Hard Work Safe
============================================

If you really need this, odds are good you are working on a big database. I created this while developing a 100+ table database storing nearly 1/2 BILLION rows of data. The thought of loosing my index information made me shudder.... so I buit automatic crash recovery and history logging into this class! You don't need to do anything. As long as your mysqli connection user has sufficient privlidges to alter the database, it takes care of everything for you. There are two logs created, a recovery log and a history log. The recovery log table name is 'SYSTEM-index-recovery-log'. The history log table name is 'SYSTEM-index-history-log'. The history log is just that, a timestamped history log of your use of this class, think of it as a backup log to the recovery log, which is totally dynamic. An empty recovery log is a happy recovery log :)
