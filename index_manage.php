<?php

class indexManage{

	### NOTE! THIS CLASS ONLY MANAGES THE ADDING AND DROPPING OF *NON-UNIQUE / PRIMARY KEY* INDEXES!
    
    private $table;
    
    private $dbConn;

    private $dbNameRes;

    private $dbNameRow;

    private $dbName;

    private $bTreeIndexRes;

    private $fullTextIndexRes;

    private $indexToDrop;

    private $bTreeIndexToAdd;

    private $fullTextIndexToAdd;

    private $timestamp;

    private $bTreeRecoverRes;

    private $fullTextRecoverRes;

    private $bTreeIndexToRecover;

    private $fullTextIndexToRecover;

    private $tableCheckRs;
    
    function __construct($inputTable, $inputDbConnect){
        
        $this->table = $inputTable;
        
        $this->dbConn = $inputDbConnect;

        $this->timestamp = date("Y-m-d H:i:s");

        # get current database name

        $this->dbNameRes = $this->dbConn->query("SELECT database() AS `db`")or die($this->dbConn->error);

        $this->dbNameRow = mysqli_fetch_all($this->dbNameRes,MYSQLI_ASSOC);

        $this->dbName = $this->dbNameRow[0]['db'];

        # check if table exists in database

        $this->tableCheckRs = $this->dbConn->query("

            SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '".addslashes($this->dbName)."' AND TABLE_NAME = '".$this->table."'

        ")or die($this->dbConn->error);

        # send out error message if database table does not exist

        if($this->tableCheckRs->num_rows == 0){

            echo "<b>ERROR! TABLE '".$this->table."' DOES NOT EXIST! TABLE NAMES ARE CaSe SeNsItIvE!</b><br>";

        }

        # create index history log if not already existing

        $this->dbConn->query("

        	CREATE TABLE IF NOT EXISTS `SYSTEM-index-history-log` (
			  `PID` bigint(20) NOT NULL AUTO_INCREMENT,
			  `timestamp` timestamp NULL DEFAULT NULL,
			  `TABLE_CATALOG` varchar(512) NOT NULL DEFAULT '',
			  `TABLE_SCHEMA` varchar(64) NOT NULL DEFAULT '',
			  `TABLE_NAME` varchar(64) NOT NULL DEFAULT '',
			  `NON_UNIQUE` bigint(1) NOT NULL DEFAULT '0',
			  `INDEX_SCHEMA` varchar(64) NOT NULL DEFAULT '',
			  `INDEX_NAME` varchar(64) NOT NULL DEFAULT '',
			  `SEQ_IN_INDEX` bigint(2) NOT NULL DEFAULT '0',
			  `COLUMN_NAME` varchar(64) NOT NULL DEFAULT '',
			  `COLLATION` varchar(1) DEFAULT NULL,
			  `CARDINALITY` bigint(21) DEFAULT NULL,
			  `SUB_PART` bigint(3) DEFAULT NULL,
			  `PACKED` varchar(10) DEFAULT NULL,
			  `NULLABLE` varchar(3) NOT NULL DEFAULT '',
			  `INDEX_TYPE` varchar(16) NOT NULL DEFAULT '',
			  `COMMENT` varchar(16) DEFAULT NULL,
			  `INDEX_COMMENT` varchar(1024) NOT NULL DEFAULT '',
			  PRIMARY KEY (`PID`),
			  KEY `TABLE_NAME` (`TABLE_NAME`),
			  KEY `TABLE_SCHEMA` (`TABLE_SCHEMA`),
			  KEY `INDEX_TYPE` (`INDEX_TYPE`),
			  KEY `NON_UNIQUE` (`NON_UNIQUE`),
			  KEY `timestamp` (`timestamp`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;


        ")or die($this->dbConn->error);


        # create index recovery log if not already existing

        $this->dbConn->query("

        	CREATE TABLE IF NOT EXISTS `SYSTEM-index-recovery-log` (
			  `PID` bigint(20) NOT NULL AUTO_INCREMENT,
			  `timestamp` timestamp NULL DEFAULT NULL,
			  `TABLE_CATALOG` varchar(512) NOT NULL DEFAULT '',
			  `TABLE_SCHEMA` varchar(64) NOT NULL DEFAULT '',
			  `TABLE_NAME` varchar(64) NOT NULL DEFAULT '',
			  `NON_UNIQUE` bigint(1) NOT NULL DEFAULT '0',
			  `INDEX_SCHEMA` varchar(64) NOT NULL DEFAULT '',
			  `INDEX_NAME` varchar(64) NOT NULL DEFAULT '',
			  `SEQ_IN_INDEX` bigint(2) NOT NULL DEFAULT '0',
			  `COLUMN_NAME` varchar(64) NOT NULL DEFAULT '',
			  `COLLATION` varchar(1) DEFAULT NULL,
			  `CARDINALITY` bigint(21) DEFAULT NULL,
			  `SUB_PART` bigint(3) DEFAULT NULL,
			  `PACKED` varchar(10) DEFAULT NULL,
			  `NULLABLE` varchar(3) NOT NULL DEFAULT '',
			  `INDEX_TYPE` varchar(16) NOT NULL DEFAULT '',
			  `COMMENT` varchar(16) DEFAULT NULL,
			  `INDEX_COMMENT` varchar(1024) NOT NULL DEFAULT '',
			  PRIMARY KEY (`PID`),
			  KEY `TABLE_NAME` (`TABLE_NAME`),
			  KEY `TABLE_SCHEMA` (`TABLE_SCHEMA`),
			  KEY `INDEX_TYPE` (`INDEX_TYPE`),
			  KEY `NON_UNIQUE` (`NON_UNIQUE`),
			  KEY `timestamp` (`timestamp`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;


        ")or die($this->dbConn->error);


        # CHECK FOR BROKEN INDEXES AND RECOVER THEM





        # checking for broken btree indexes

        $this->bTreeRecoverRes = $this->dbConn->query("

			SELECT

					`a`.`table`,
				    `a`.`index`,
				    CONCAT('(', GROUP_CONCAT(`a`.`column` ORDER BY `a`.`SEQ_IN_INDEX`), ')') AS `columns` 

				FROM

					(

                        SELECT

                        DISTINCT 

                        `".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`.TABLE_NAME AS `table`, 
                        CONCAT('`',`".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`.INDEX_NAME,'`') AS `index`, 
                        CONCAT_WS('', CONCAT('`',`".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`.COLUMN_NAME,'`'), CONCAT('(',`".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`.SUB_PART,')')) AS `column`,
                        `".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`.SEQ_IN_INDEX

                        FROM 

                            `".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log` 

                        LEFT JOIN

                            information_schema.STATISTICS

                            ON

                            information_schema.STATISTICS.TABLE_NAME = `".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`.`TABLE_NAME`

                            AND

                            information_schema.STATISTICS.INDEX_NAME = `".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`.`INDEX_NAME`

                            AND

                            information_schema.STATISTICS.COLUMN_NAME = `".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`.`COLUMN_NAME`

                        WHERE

                                information_schema.STATISTICS.TABLE_NAME IS NULL

                            AND

                                information_schema.STATISTICS.INDEX_NAME IS NULL

                            AND

                                information_schema.STATISTICS.COLUMN_NAME IS NULL

                            AND

                                `".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`.NON_UNIQUE = '1'

                            AND

                                `".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`.INDEX_TYPE = 'BTREE'

                            AND

                                `".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`.TABLE_SCHEMA = '".addslashes($this->dbName)."' 

                            AND 

                                `".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`.TABLE_NAME = '".addslashes($this->table)."'
                        
				     ) AS `a`
				     
				GROUP BY `a`.`table`, `a`.`index`

        ")or die($this->dbConn->error);

        # check results

        if($this->bTreeRecoverRes->num_rows > 0){

        	echo "<hr><h3>Recovering Lost BTREE Indexes for Table: ". $this->table."</h3>";

        	# recover lost btree indexes

	        $this->bTreeIndexToRecover = array();

	    	foreach($this->bTreeRecoverRes as $row){

	    		echo "INDEX: " . $row['index'] . " ----> COLUMNS: " .$row['columns']. "<br>";

	    		$this->bTreeIndexToRecover[] = "ADD KEY ". $row['index'] . " " . $row['columns'];
	    		
	    	}

	    	## recover table btree indexes in one go

	    	$this->dbConn->query("

	    		ALTER TABLE `".addslashes($this->table)."` ".implode(',', $this->bTreeIndexToRecover)."

	    	")or die($this->dbConn->error);

	    	# clear array

	    	$this->bTreeIndexToRecover = [];

	    	echo "<br>Recovery Done!";

        }






        # checking for broken fulltext indexes

       $this->fullTextRecoverRes = $this->dbConn->query("

			SELECT

					`a`.`table`,
				    `a`.`index`,
				    CONCAT('(', GROUP_CONCAT(`a`.`column` ORDER BY `a`.`SEQ_IN_INDEX`), ')') AS `columns` 

				FROM

					(

                        SELECT

                        DISTINCT 

                        `".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`.TABLE_NAME AS `table`, 
                        CONCAT('`',`".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`.INDEX_NAME,'`') AS `index`, 
                        CONCAT_WS('', CONCAT('`',`".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`.COLUMN_NAME,'`'), CONCAT('(',`".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`.SUB_PART,')')) AS `column`,
                        `".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`.SEQ_IN_INDEX

                        FROM 

                            `".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log` 

                        LEFT JOIN

                            information_schema.STATISTICS

                            ON

                            information_schema.STATISTICS.TABLE_NAME = `".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`.`TABLE_NAME`

                            AND

                            information_schema.STATISTICS.INDEX_NAME = `".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`.`INDEX_NAME`

                            AND

                            information_schema.STATISTICS.COLUMN_NAME = `".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`.`COLUMN_NAME`

                        WHERE

                                information_schema.STATISTICS.TABLE_NAME IS NULL

                            AND

                                information_schema.STATISTICS.INDEX_NAME IS NULL

                            AND

                                information_schema.STATISTICS.COLUMN_NAME IS NULL

                            AND

                                `".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`.NON_UNIQUE = '1'

                            AND

                                `".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`.INDEX_TYPE = 'FULLTEXT'

                            AND

                                `".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`.TABLE_SCHEMA = '".addslashes($this->dbName)."' 

                            AND 

                                `".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`.TABLE_NAME = '".addslashes($this->table)."'
                        
				     ) AS `a`
				     
				GROUP BY `a`.`table`, `a`.`index`

        ")or die($this->dbConn->error);

        # check results

        if($this->fullTextRecoverRes->num_rows > 0){

        	echo "<h3>Recovering Lost FULLTEXT Indexes for Table: ". $this->table."</h3>";

        	# recover lost fulltext indexes

	        $this->fullTextIndexToRecover = array();

	    	foreach($this->fullTextRecoverRes as $row){

	    		echo "INDEX: " . $row['index'] . " ----> COLUMNS: " .$row['columns']. "<br>";

	    		$this->fullTextIndexToRecover[] = "ADD FULLTEXT KEY ". $row['index'] . " " . $row['columns'];
	    		
	    	}

	    	## recover table fulltext indexes one at a time

	    	foreach($this->fullTextIndexToRecover as $row){

	    		$this->dbConn->query("

	    			ALTER TABLE `".addslashes($this->table)."` ".$row."

	    		")or die($this->dbConn->error);

	    	}

	    	# clear array

	    	$this->fullTextIndexToRecover = [];

	    	echo "<br>Recovery Done!<hr>";

        }


        # DELETE ANY ERROR LOG ITEMS THAT EXIST FOR THIS TABLE


        $this->dbConn->query("

        	DELETE FROM 

        		`SYSTEM-index-recovery-log` 

        	WHERE

					TABLE_SCHEMA = '".addslashes($this->dbName)."' 

				AND 

				    TABLE_NAME = '".addslashes($this->table)."'

        ")or die($this->dbConn->error);
		
		

        # LOG CURRENT STATE OF INDEX

        # create index history log - for convinence and possible use in recovery if recovery log is affected during application crash

        $this->dbConn->query("


					INSERT INTO `".addslashes($this->dbName)."`.`SYSTEM-index-history-log`
                    
                            (
                            	timestamp,
                                TABLE_CATALOG,
                                TABLE_SCHEMA,
                                TABLE_NAME,
                                NON_UNIQUE,
                                INDEX_SCHEMA,
                                INDEX_NAME,
                                SEQ_IN_INDEX,
                                COLUMN_NAME,
                                COLLATION,
                                CARDINALITY,
                                SUB_PART,
                                PACKED,
                                NULLABLE,
                                INDEX_TYPE,
                                COMMENT,
                             	INDEX_COMMENT
                            )
                    
                    SELECT 

                    		'".addslashes($this->timestamp)."' as timestamp,
				            TABLE_CATALOG,
                            TABLE_SCHEMA,
                            TABLE_NAME,
                            NON_UNIQUE,
                            INDEX_SCHEMA,
                            INDEX_NAME,
                            SEQ_IN_INDEX,
                            COLUMN_NAME,
                            COLLATION,
                            CARDINALITY,
                            SUB_PART,
                            PACKED,
                            NULLABLE,
                            INDEX_TYPE,
                            COMMENT,
                            INDEX_COMMENT
                            

				        FROM 

				            information_schema.STATISTICS

				        WHERE 
	                    
	                    		NON_UNIQUE = '1'
	                    
	                    	AND

				                TABLE_SCHEMA = '".addslashes($this->dbName)."' 

				            AND 

				                TABLE_NAME = '".addslashes($this->table)."'

        ")or die($this->dbConn->error);


        # STORE CURRENT INDEXES FOR OBJECT TO ACCESS

        # get current table non-unique btree indexes

        $this->bTreeIndexRes = $this->dbConn->query("

			SELECT

					`a`.`table`,
				    `a`.`index`,
				    CONCAT('(', GROUP_CONCAT(`a`.`column` ORDER BY `a`.`SEQ_IN_INDEX`), ')') AS `columns` 

				FROM

					(

				        SELECT 

				            TABLE_NAME AS `table`, 
				            CONCAT('`',INDEX_NAME,'`') AS `index`, 
				            CONCAT_WS('', CONCAT('`',COLUMN_NAME,'`'), CONCAT('(',SUB_PART,')')) AS `column`,
				            SEQ_IN_INDEX

				        FROM 

				            information_schema.STATISTICS

				        WHERE 
	                    
	                    		NON_UNIQUE = '1'

	                    	AND

	                    		INDEX_TYPE = 'BTREE'
	                    
	                    	AND

				                TABLE_SCHEMA = '".addslashes($this->dbName)."' 

				            AND 

				                TABLE_NAME = '".addslashes($this->table)."'
				        
				     ) AS `a`
				     
				GROUP BY `a`.`table`, `a`.`index`

        ")or die($this->dbConn->error);


        # get current table non-unique fulltext indexes

        $this->fullTextIndexRes = $this->dbConn->query("

			SELECT

					`a`.`table`,
				    `a`.`index`,
				    CONCAT('(', GROUP_CONCAT(`a`.`column` ORDER BY `a`.`SEQ_IN_INDEX`), ')') AS `columns` 

				FROM

					(

				        SELECT 

				            TABLE_NAME AS `table`, 
				            CONCAT('`',INDEX_NAME,'`') AS `index`, 
				            CONCAT_WS('', CONCAT('`',COLUMN_NAME,'`'), CONCAT('(',SUB_PART,')')) AS `column`,
				            SEQ_IN_INDEX

				        FROM 

				            information_schema.STATISTICS

				        WHERE 
	                    
	                    		NON_UNIQUE = '1'

	                    	AND

	                    		INDEX_TYPE = 'FULLTEXT'
	                    
	                    	AND

				                TABLE_SCHEMA = '".addslashes($this->dbName)."' 

				            AND 

				                TABLE_NAME = '".addslashes($this->table)."'
				        
				     ) AS `a`
				     
				GROUP BY `a`.`table`, `a`.`index`

        ")or die($this->dbConn->error);

        
    }
    
    function getTableName(){
    
        return $this->table;
    
    }

    function getDbName(){

    	return $this->dbName;

    }

    function sayIndexes(){

    	echo "<hr>";

    	foreach($this->bTreeIndexRes as $row){

    		echo "<h3>" . $this->table . " *NON-UNIQUE* BTREE INDEXES</h3>";

    		echo "INDEX NAME: ". $row['index'] . " -----> COLUMNS: " .$row['columns']. "<br>";
    	}



    	foreach($this->fullTextIndexRes as $row){

    		echo "<h3>" . $this->table . " *NON-UNIQUE* FULLTEXT INDEXES</h3>";

    		echo "INDEX NAME: ". $row['index'] . " -----> COLUMNS: " .$row['columns']. "<br>";
    	}

    	echo "<hr>";

    }


    function dropIndexes(){

    	# create index recovery log - used by crash recovery code in class construct to recover any possible broken indexes after application crash, etc.

        $this->dbConn->query("


					INSERT INTO `".addslashes($this->dbName)."`.`SYSTEM-index-recovery-log`
                    
                            (
                            	timestamp,
                                TABLE_CATALOG,
                                TABLE_SCHEMA,
                                TABLE_NAME,
                                NON_UNIQUE,
                                INDEX_SCHEMA,
                                INDEX_NAME,
                                SEQ_IN_INDEX,
                                COLUMN_NAME,
                                COLLATION,
                                CARDINALITY,
                                SUB_PART,
                                PACKED,
                                NULLABLE,
                                INDEX_TYPE,
                                COMMENT,
                             	INDEX_COMMENT
                            )
                    
                    SELECT 

                    		'".addslashes($this->timestamp)."' as timestamp,
				            TABLE_CATALOG,
                            TABLE_SCHEMA,
                            TABLE_NAME,
                            NON_UNIQUE,
                            INDEX_SCHEMA,
                            INDEX_NAME,
                            SEQ_IN_INDEX,
                            COLUMN_NAME,
                            COLLATION,
                            CARDINALITY,
                            SUB_PART,
                            PACKED,
                            NULLABLE,
                            INDEX_TYPE,
                            COMMENT,
                            INDEX_COMMENT
                            

				        FROM 

				            information_schema.STATISTICS

				        WHERE 
	                    
	                    		NON_UNIQUE = '1'
	                    
	                    	AND

				                TABLE_SCHEMA = '".addslashes($this->dbName)."' 

				            AND 

				                TABLE_NAME = '".addslashes($this->table)."'

        ")or die($this->dbConn->error);

        # DROP INDEXES

    	$this->indexToDrop = array();

    	foreach($this->bTreeIndexRes as $row){

    		$this->indexToDrop[] = "DROP KEY ". $row['index'];
    		
    	}

    	foreach($this->fullTextIndexRes as $row){

    		$this->indexToDrop[] = "DROP KEY ". $row['index'];
    		
    	}

    	$this->dbConn->query("

    		ALTER TABLE `".addslashes($this->table)."` ".implode(',', $this->indexToDrop)."

    	")or die($this->dbConn->error);

    	# clear array

    	$this->indexToDrop = [];

    }


    function rebuildIndexes(){

    	$this->bTreeIndexToAdd = array();

    	foreach($this->bTreeIndexRes as $row){

    		$this->bTreeIndexToAdd[] = "ADD KEY ". $row['index'] . " " . $row['columns'];
    		
    	}

    	$this->fullTextIndexToAdd = array();

    	foreach($this->fullTextIndexRes as $row){

    		$this->fullTextIndexToAdd[] = "ADD FULLTEXT KEY ". $row['index'] . " " . $row['columns'];
    		
    	}

    	## rebuild table btree indexes in one go

    	$this->dbConn->query("

    		ALTER TABLE `".addslashes($this->table)."` ".implode(',', $this->bTreeIndexToAdd)."

    	")or die($this->dbConn->error);


    	## rebuild table fulltext indexes one at a time

    	foreach($this->fullTextIndexToAdd as $row){

    		$this->dbConn->query("

    			ALTER TABLE `".addslashes($this->table)."` ".$row."

    		")or die($this->dbConn->error);

    	}

    	# clear array

    	$this->bTreeIndexToAdd = [];

    	$this->fullTextIndexToAdd = [];

    	# INDEXES REBUILT

    	# dump index recovery information

        $this->dbConn->query("

        	DELETE FROM 

        		`SYSTEM-index-recovery-log` 

        	WHERE

					TABLE_SCHEMA = '".addslashes($this->dbName)."' 

				AND 

				    TABLE_NAME = '".addslashes($this->table)."'

				AND

					timestamp = '".addslashes($this->timestamp)."'

        ")or die($this->dbConn->error);

    }

}

?>
