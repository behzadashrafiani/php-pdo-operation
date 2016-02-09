<?php
class DataBases
{
    private $_strDBHost;
    private $_strDBName;
    private $_strDBUser;
    private $_strDBPassword;
    private $_strDBSName;
    private $_strDBSUser;
    private $_strDBSPassword;
    private $db;
	private $_func;

    function __construct($params=null){
  		$this->_strDBHost=($params['host'])?$params['host']:"your host name";
  		$this->_strDBName=($params['db'])?$params['db']:"db name";
  		$this->_strDBUser=($params['user'])?$params['user']:"db user";
  		$this->_strDBPassword=($params['pass'])?$params['pass']:"password";
    }

    function pdoConnect($dbs=null){
		if($dbs){
			$dbname=$this->_strDBSName;
			$dbuser=$this->_strDBSUser;
			$dbpass=$this->_strDBSPassword;
		}else{
			$dbname=$this->_strDBName;
			$dbuser=$this->_strDBUser;
			$dbpass=$this->_strDBPassword;
		}
        try {
            ini_set('max_execution_time', 300);
            $dsn = "mysql:host=". $this->_strDBHost.";dbname=".$dbname;
            $db = new PDO($dsn,"$dbuser", "$dbpass");
	          $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $db->exec("SET names utf8");
            $db->exec("SET time_zone = 'your timezone'");
        }
        catch (PDOException $e) {
            print "Error!: " . $e->getMessage() . "<br/>";
            die();
        }
        return $db;
    }

	

	function insert($tblName, $fields, $checkRefferer=1, $checkAccess=1, $trackMode=null){
		//$track=new Track();
		if(!$trackMode && $checkAccess){
			$access=$this->checkAccess('insert', $tblName, $checkRefferer);
			if(!$access){
				//$track->track('no access insert', null, null, $tblName);
				exit;
			}
		}
		$db=$this->pdoConnect();
		$pdoSql="INSERT INTO `$tblName` (";
		foreach($fields as $key=>$value)
		{
			$fieldStr.="`$key`,";
			$paramStr.=":$key,";
			$params[":$key"]="$value";
		}
		$fieldStr=substr($fieldStr,0,strlen($fieldStr)-1);
		$paramStr=substr($paramStr,0,strlen($paramStr)-1);
		$pdoSql.= $fieldStr ." ) VALUES ( " . $paramStr . " ) ";
		$res=$db->prepare($pdoSql);
		if($res->execute($params)){
			$lid=$db->lastInsertId();
			//if(!$trackMode) $track->track('insert', null, null, $tblName, $lid);
			return $lid;
		}else return false;
	}

	function addQ($e){
		return "'$e'";
	}

	function update($tblName, $fields, $whereClause=null, $params=null, $returnType=null, $checkRefferer=1, $checkAccess=1, $trackMode=null){
		//$track=new Track();
		$pdoSql="UPDATE `$tblName` SET ";
		foreach($fields as $key=>$value)
		{
			$val=explode(':', $value);
			if($val[0]=='STATIC_VALUE'){
				$value=$val[1];
				$fieldStr.="`$key` = $value ,";
			}else{
				$fieldStr.="`$key` = :$key ,";
				$params[":$key"]=$value;
			}
		}
		$fieldStr=substr($fieldStr, 0, strlen($fieldStr)-1);
		$pdoSql.=$fieldStr;
		if($whereClause) $pdoSql.= " WHERE $whereClause";
		//$strParams=serialize($params);

		if(!$trackMode && $checkAccess){
			if(!$this->checkAccess('update', $tblName, $checkRefferer)){
				//$track->track('no access update', null, null, $tblName, null, $pdoSql, $strParams);
				header("location:index.php?mod=message&act=1&mess=2");
				exit;
			}
		}
		if($returnType){
			$vals=array_values($params);
			$vals=array_map('addQ', $vals);
			$showPdoSql=str_replace(array_keys($params), $vals, $pdoSql);
			return $showPdoSql;
		}
		$db=$this->pdoConnect();
		$res=$db->prepare($pdoSql);
		if($res->execute($params)){
			// print "<font color='green'>".str_replace(array_keys($params), array_values($params), $pdoSql)."</font><br>";
			//if(!$trackMode) $track->track('update', null, null, $tblName, null, $pdoSql, $strParams);
			return true;
		}else
			// print "<font color='red'>".str_replace(array_keys($params), array_values($params), $pdoSql)."</font><br>";
			return false;
	}

	function delete($tblName, $whereClause=null, $params=null, $checkRefferer=1, $checkAccess=1, $trackMode=null){
		//$track=new Track();
		$pdoSql="UPDATE `$tblName` SET deleted=1";
		if($whereClause) $pdoSql.=" where $whereClause";
		//$strParams=serialize($params);

		$lastWeek=date('Y-m-d H:i:s', time()-3600*24*7);
		$this->runQuery("delete from `$tblName` where deleted=1 and tstmp<:time", array(':time'=>$lastWeek));

		if(!$trackMode && $checkAccess){
			if(!$this->checkAccess('delete', $tblName, $checkRefferer)){
				//$track->track('no access delete', null, null, $tblName, null, $pdoSql, $strParams);
				header("location:index.php?mod=message&act=1&mess=2");
				exit;
			}
		}
		$db=$this->pdoConnect();
		$res=$db->prepare($pdoSql);
		if($res->execute($params)){
			//if(!$trackMode) $track->track('delete', null, null, $tblName, null, $pdoSql, $strParams);
			return true;
		}else{
			return false;
		}
	}

    function select($selectArray, $params=null, $returnType=null, $paginating=null, $trackMode=null, $dbs=null){
		//$track=new Track();
		$query="select ";
		//DISTINCT-----------------------
		if($selectArray['distinct']) $query.=$selectArray['distinct']." ";
		if(!$selectArray['fields']) return false;
		if(is_array($selectArray['fields'])){
			foreach($selectArray['fields'] as $field){
				$query.="$field, ";
			}
			$query=substr($query, 0, strlen($query)-2);
		}else $query.="$selectArray[fields] ";
		$selectLen=strlen($query);
		//FROM----------------------------
		$query.=" from ";
		if(!$selectArray['tables']) return false;
		if(is_array($selectArray['tables'])){
			for($i=0; $i<count($selectArray['tables']); $i++){
				$table=$selectArray['tables'][$i];
				$table=str_replace(' as ', ' ', $table);
				if(in_array(' ', str_split($table))){
					$tableNameArray=explode(' ', $table);
					$tableFullName[$tableNameArray[1]]=$tableNameArray[0];
					$tableNickName[$tableNameArray[0]]=$tableNameArray[1];
				}else $tableNameArray[0]=$table;
				$thisTable=$this->runQuery("show tables like '$tableNameArray[0]'");
				if(!$thisTable->rowCount()) continue;
				if($i>0){
					if(is_array($selectArray['connectors']))
						$query.=$selectArray['connectors'][$i-1]." ";
					else
						$query.=$selectArray['connectors']." ";
				}
				$query.=$selectArray['tables'][$i]." ";
				if($i>0){
					$tna=($tableNameArray[1])?$tableNameArray[1]:$tableNameArray[0];
					if(is_array($selectArray['on']))
						$query.="on (".$selectArray['on'][$i-1]." and ".$tna.".deleted=0) ";
					else
						$query.="on (".$selectArray['on']." and ".$tna.".deleted=0) ";
				}
			}
		}else{
			$table=$selectArray['tables'];
			$table=str_replace(' as ', ' ', $table);
			if(in_array(' ', str_split($table))){
				$tableNameArray=explode(' ', $table);
				$tableFullName[$tableNameArray[1]]=$tableNameArray[0];
				$tableNickName[$tableNameArray[0]]=$tableNameArray[1];
			}
			$thisTable=$this->runQuery("show tables like '$tableNameArray[0]'");
			if(!$thisTable->rowCount()) return null;
			$query.=$selectArray['tables']." ";
		}
		//WHERE--------------------------
		if($selectArray['where']){
			$query.="where ($selectArray[where]) ";
			if(is_array($selectArray['tables'])){
				$firstTable=$selectArray['tables'][0];
				$firstTable=str_replace(" as ", " ", $firstTable);
				$firstTable=explode(" ", $firstTable);
				if($firstTable[1]) $firstTable=$firstTable[1];
				else $firstTable=$firstTable[0];
				$query.="and $firstTable.deleted=0 ";
			}else{
				$table=$selectArray['tables'];
				$table=str_replace(" as ", " ", $table);
				$table=explode(" ", $table);
				if($table[1]) $table=$table[1];
				else $table=$table[0];
				$query.="and $table.deleted=0 ";
			}
		}else{
			if(is_array($selectArray['tables'])){
				$firstTable=$selectArray['tables'][0];
				$firstTable=str_replace(" as ", " ", $firstTable);
				$firstTable=explode(" ", $firstTable);
				if($firstTable[1]) $firstTable=$firstTable[1];
				else $firstTable=$firstTable[0];
				$query.="where $firstTable.deleted=0 ";
			}else{
				$table=$selectArray['tables'];
				$table=str_replace(" as ", " ", $table);
				$table=explode(" ", $table);
				if($table[1]) $table=$table[1];
				else $table=$table[0];
				$query.="where $table.deleted=0 ";
			}
		}
		//GROUP BY-----------------------
		if($selectArray['group']){
			$query.="group by ";
			if(is_array($selectArray['group'])) foreach($selectArray['group'] as $grp) $query.="$grp, ";
			else $query.=$selectArray['group'];
		}
		//ORDER BY-----------------------
		if($selectArray['order']){
			$query.=" order by ";
			if(is_array($selectArray['order'])){
				foreach($selectArray['order'] as $ord){
					$query.="$ord, ";
				}
				$query=substr($query, 0, strlen($query)-2);
			}else{
				$query.=$selectArray['order'];
			}
		}

		//LIMIT----------------------------
		if($selectArray['limit']){
			$query.=" limit ".$selectArray['limit'];
		}
		if($returnType==1) return $query;

		// if($params && is_array($params))
			// print "query : ".str_replace(array_keys($params), array_values($params), $query)."<br/>";
		// print_r($params);
		// print "<br>";

		//$strParams=serialize($params);
		//if(!$trackMode) $track->track('select', null, null, null, null, $query, $strParams);
		//Paginating-----------------------
		try {
			if($paginating && is_array($paginating)){
				$perpage=$paginating["perpage"];
				if(!$paginating["page"] || !is_numeric($paginating["page"])) $paginating["page"]=1;
				$start=($paginating["page"]-1)*$perpage;
				if($paginating['baseTable']){
					$countQuery="select count(*) from $paginating[baseTable] where deleted=0";
				}else{
					$countQuery="select count(*) ".substr($query, $selectLen);
				}
				$db=$this->pdoConnect($dbs);
				$res=$db->prepare($countQuery);
				$res->execute($params);
				$row=$res->fetch();
				$total=$row[0];
				$query.=" limit $start, $perpage";
				$res=$db->prepare($query);
				$res->execute($params);
				$val=$res->fetchAll();
				$pages=ceil($total/$perpage);
				return array(
					'total'=>$total,
					'pages'=>$pages,
					'val'=>$val,
				);
			}else{
				$db=$this->pdoConnect($dbs);
				$res=$db->prepare($query);
				$res->execute($params);
				$val=$res->fetchAll();
				return $val;
			}
		}
		catch (PDOException $e) {
			print "Error!: " . $e->getMessage() . "<br/>";
			die();
		}
    }

	function runQuery($query, $params=null, $trackMode=null){
		//$track=new Track();
		//$strParams=serialize($params);
		//if(!$trackMode) $track->track('select', null, null, null, null, $query, $strParams);
		$db=$this->pdoConnect();
		$res=$db->prepare($query);
		$res->execute($params);
		return $res;
	}
}
?>
