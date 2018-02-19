<?php


function getFileMakerConnection()
{
	global $FM_CONNECTION, $FM_DATABASE, $FM_HOSTSPEC, $FM_USERNAME, $FM_PASSWORD;

	if (!isset($GLOBALS['FM_CONNECTION']) || !$FM_CONNECTION)
	{
		$FM_CONNECTION = new \FileMaker();
		$FM_CONNECTION->setProperty('database', $FM_DATABASE);
		$FM_CONNECTION->setProperty('hostspec', $FM_HOSTSPEC);
		$FM_CONNECTION->setProperty('username', $FM_USERNAME);
		$FM_CONNECTION->setProperty('password', $FM_PASSWORD);
		//echo "username: " . $FM_USERNAME . " password: " . $FM_PASSWORD . "<br/>";
	}

	return $FM_CONNECTION;
}

function mysqlConnection() {
    global $con;
    $con = mysqli_connect('localhost', 'mcgraw_kirill', 'Indormitable123$', 'mcgraw_assets');
    return $con;
}

function throwExceptionOnFMError($obj)
{
	global $FM_CONNECTION;
	
	if ($FM_CONNECTION->isError($obj))
	{
		$backtrace = debug_backtrace();
		throw new \ErrorException($obj->getMessage() . "(code: {$obj->getCode()}, file: {$backtrace[0]['file']}, line: {$backtrace[0]['line']})");
	}
}

function debug_to_console( $data ) {
    $output = $data;
    if ( is_array( $output ) )
        $output = implode( ',', $output);

    echo "<script>console.log( 'Debug Objects:  " . var_dump($output) . "' );</script>";
}

function login($userName, $password){

    //echo "attempting login with " . $userName . " and " . $password . "<br/>";
    $hash = md5($password);
    $con = mysqlConnection();

    $query = "select * from Users where username = '$userName' and hash = '$hash'";
    $rowCount = 0;
    if($res = mysqli_query($con, $query)) {
        $rowCount = mysqli_num_rows($res);
        mysqli_free_result($res);
        mysqli_close($con);
    }
    return $rowCount;
}

function signup($post){
    $page = 'Users';
    $fields = getFieldNames($page);
    $fm = getFileMakerConnection();
    $cmd = $fm->newAddCommand('PHP-' . $page);
    
    $result = $cmd->execute();
    
    if ($fm->isError($result))
    {
        //echo "Error - " . $result->getMessage();
        throw new \ErrorException("New DBItem Failed ");
    }

    
    $records = $result->getRecords();
    $salt = $records[0]->_impl->_fields['salt'][0];
    $post['hash'] =  md5( $salt . $post['password']);
    $post['access'] = 'Internal';
    $dbItem = null;
    if(count($records)> 0){
        $record = $records[0];
           $dbItem = new DBItem();

        $dbItem->setDBItemFields($fields);
        $dbItem->reInit($record);
    }
    if(!empty($dbItem)){

        //$dbItem->setName();
        // echo "setting store to ";
        // //var_dump($post);
        $dbItem->preSet($post);
         $dbItem->commitToDatabase();
        // echo "record committed";
    }
}


function getDBItems($page){
    $con = mysqlConnection();
    $query = "select * from $page";
    $rows = array();
    if($res = mysqli_query($con, $query)) {
        while($row = mysqli_fetch_assoc($res)) {
            array_push($rows, $row);  
        }
    }
    return $rows;
}

function getFields($page){
    $con = mysqlConnection();
    $query = "select * from PHP_Formfields where page = '$page' order by sort";
    $tabs = [];
    if($res = mysqli_query($con, $query)) {
        while($record = mysqli_fetch_assoc($res)) {
            $field = new Field($record);
            $parentTab = trim($field->parentTab);
            if(empty($parentTab)){
                if(empty($tabs[$field->tab])){
                    $tabs[$field->tab] = [];
                }
                $tabs[$field->tab][] = $field;
            }else{
                if(empty($tabs[$parentTab][$field->tab])){
                    $tabs[$parentTab][$field->tab] = [];

                }
                $tabs[$parentTab][$field->tab][] = $field;
    /*           
                echo ("<pre>");
                var_dump($tabs[$parentTab]);
                echo("</pre>");
    */
            }
        }
    }
    return $tabs;
}


function getFieldNames($page){

    // $fm = getFileMakerConnection();
    // $cmd = $fm->newFindCommand('PHP-FORMFIELDS');
    // $cmd->addFindCriterion('page', '=='.$page);
    // //echo "finding fields for page " . $page . "<br>";
    // //cmd->addFindCriterion('username', '=='.$userName);
    // $cmd->addSortRule('sort', 1, FILEMAKER_SORT_ASCEND);
    // $result = $cmd->execute();
    
    // if ($fm->isError($result))
    // {
    //     //echo "Error - " . $result->getMessage();
    //     throw new \ErrorException("Get Field Names Failed");
    // }

    
    // $records = $result->getRecords();

    // $fields = [];
    // foreach ($records as $record) {
    //     $field = new Field($record);
    //     $fields[] = $field->name;
    // }

    // return $fields;

    $con = mysqlConnection();
    $query = "select * from $page";
    $field_names = array();
    if($res = mysqli_query($con, $query)) {
        $fields = mysqli_fetch_fields($res);
        foreach($fields as $field) {
            array_push($field_names, $field->name);
        }
    }
    return $field_names;

}

function getSelectList($page){

    $fm = getFileMakerConnection();
    $cmd = $fm->newFindCommand('PHP-' . $page);
    //echo "finding fields for page " . $page . "<br>";
    //cmd->addFindCriterion('username', '=='.$userName);
    $cmd->addSortRule('name', 1, FILEMAKER_SORT_DESCEND);
    $result = $cmd->execute();
    
    if ($fm->isError($result))
    {
        //echo "Error - " . $result->getMessage();
        throw new \ErrorException("Get Field Names Failed");
    }

    
    $records = $result->getRecords();

    $items = [];
    foreach ($records as $record) {
        $items[$record->getField('id')] = $record->getField('Name');
    }

    return $items;

}

function getDBItem($dbItemID,$page){

    $con = mysqlConnection();
    $query = "select * from $page where id = '$dbItemID'";
    $row = array();
    if($res = mysqli_query($con, $query)) {
        $row = mysqli_fetch_assoc($res);
    }
    return $row;

}

function setDBItem($post,$page){
    $dbItemID = $post["id"];
    //echo "setting dbItem with id " . $dbItemID . "<br/>";
    $fields = getFieldNames($page);
    $fm = getFileMakerConnection();
    $cmd = $fm->newFindCommand('PHP-'.$page);
    $cmd->addFindCriterion('id', '=='.$dbItemID);
    
    $result = $cmd->execute();
    
    if ($fm->isError($result))
    {
        //echo "Error - " . $result->getMessage();
        throw new \ErrorException("Get DBItem Failed using dbItem id ". $dbItemID);
    }

    
    $records = $result->getRecords();
    //echo "found " . count($records) . "<br/>";

    $dbItem = null;
    if(count($records)> 0){
        $record = $records[0];
           $dbItem = new DBItem();

        $dbItem->setDBItemFields($fields);
        $dbItem->reInit($record);
        //echo "record reinitialized <br/>";
    }

    if(!empty($dbItem)){
        //$dbItem->setName();
         //echo "setting store to ";
         //var_dump($post);
        //echo "records id is " . $record->getField('id') . " on dbItem its " . $dbItem->id . "<br/>";
        $dbItem->preSet($post);
         $dbItem->commitToDatabase();
        //echo "record committed";
    }
}

function newDBItem($post,$page){
    $fields = getFieldNames($page);
    $keys = array();
    $values = array();
    foreach($fields as $key) {
        if($key !='id') {
            $new_key = str_replace(' ', '_', $key);
            array_push($keys, $key);
            array_push($values, $post[$new_key]);
        }
    }
    
    $con = mysqlConnection();
    $query = 'INSERT INTO `'.$page.'` (`'.implode('`,`', $keys).'`) VALUES (\''.implode('\',\'', $values).'\')';
    mysqli_query($con, $query);
    mysqli_close($con);
}
