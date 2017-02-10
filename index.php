<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
* CodeIgniter MongoDB Active Record Library
*
* A library to interface with the NoSQL database MongoDB. For more information see http://www.mongodb.org
*
* @package CodeIgniter
* @author Satish Deshumkh | www.tech-know.in
* @copyright Copyright (c) 2017, Satish Deshmukh
* @license https://opensource.org/licenses/GPL-3.0
* @link https://github.com/satishdeshmukh/lib_mongdb_php7
* @version Version 1.0
*/

Class Mongodb{

	private $CI;
	private $config = array();
	private $param = array();
	private $activate;
	private $connect;
	private $db;
	private $hostname;
	private $port;
	private $database;
	private $username;
	private $password;
	private $debug;
	private $write_concerns;
	private $journal;
	private $selects = array();
	private $update = array();
	private $where	= array();
	private $limit	= 999999;
	private $offset	= 0;
	private $sorts	= array();
	private $return_as = 'array';
	public $benchmark = array();

	/**
	* --------------------------------------------------------------------------------
	* Class Constructor
	* --------------------------------------------------------------------------------
	* Try to connect on MongoDB server.
	*/

	function __construct($param){

		$this->CI = & get_instance();
		$this->CI->load->config('mongodb');
		$this->config = $this->CI->config->item('mongodb');
		$this->param = $param;

		$this->connectMongo();

	}

	/**
	* --------------------------------------------------------------------------------
	* Prepare configuration for mongoDB connection
	* --------------------------------------------------------------------------------
	* 
	* Validate group name or autoload default group name from config file.
	* Validate all the properties present in config file of the group.
	*/

	private function prepare(){

		if(is_array($this->param) && count($this->param) > 0 && isset($this->param['activate']) == TRUE){
			$this->activate = $this->param['activate'];
		}
		else if(isset($this->config['active']) && !empty($this->config['active'])){
			$this->activate = $this->config['active'];
		}else{
			show_error("MongoDB configuration is missing.", 500);
		}

		if(isset($this->config[$this->activate]) == TRUE){
			if(empty($this->config[$this->activate]['hostname'])){
				show_error("Hostname missing from mongodb config group : {$this->activate}", 500);
			}
			else{
				$this->hostname = trim($this->config[$this->activate]['hostname']);
			}

			if(empty($this->config[$this->activate]['port'])){
				show_error("Port number missing from mongodb config group : {$this->activate}", 500);
			}
			else{
				$this->port = trim($this->config[$this->activate]['port']);
			}

			if(isset($this->config[$this->activate]['no_auth']) == FALSE
			   && empty($this->config[$this->activate]['username']))
			{
				show_error("Username missing from mongodb config group : {$this->activate}", 500);
			}
			else
			{
				$this->username = trim($this->config[$this->activate]['username']);
			}

			if(isset($this->config[$this->activate]['no_auth']) == FALSE 
			   && empty($this->config[$this->activate]['password']))
			{
				show_error("Password missing from mongodb config group : {$this->activate}", 500);
			}
			else
			{
				$this->password = trim($this->config[$this->activate]['password']);
			}

			if(empty($this->config[$this->activate]['database']))
			{
				show_error("Database name missing from mongodb config group : {$this->activate}", 500);
			}
			else
			{
				$this->database = trim($this->config[$this->activate]['database']);
			}

			if(empty($this->config[$this->activate]['db_debug']))
			{
				$this->debug = FALSE;
			}
			else
			{
				$this->debug = $this->config[$this->activate]['db_debug'];
			}

			if(empty($this->config[$this->activate]['write_concerns']))
			{
				$this->write_concerns = 1;
			}
			else
			{
				$this->write_concerns = $this->config[$this->activate]['write_concerns'];
			}

			if(empty($this->config[$this->activate]['journal']))
			{
				$this->journal = TRUE;
			}
			else
			{
				$this->journal = $this->config[$this->activate]['journal'];
			}

			if(empty($this->config[$this->activate]['return_as']))
			{
				$this->return_as = 'array';
			}
			else
			{
				$this->return_as = $this->config[$this->activate]['return_as'];
			}
		}
		else
		{
			show_error("mongodb config group :  <strong>{$this->activate}</strong> does not exist.", 500);
		}
	}


	/* Connect to MongoDb Database */

	private function connectMongo(){

		$this->prepare();

		try{
			if( isset($this->config[$this->activate]['no_auth']) && $this->config[$this->activate]['no_auth'] ){
				$options = [];
			}
			else{
				$options = ['username' => $this->username,'password' => $this->password];
			}

			$uri = "mongodb://localhost:27017";

			$this->connect = new MongoDB\Driver\Manager($uri);

			$this->db = $this->database;

		}
		catch(MongoConnectionException $e){

			if( isset($this->debug) && $this->debug ){
				show_error("Unable to connect to MongoDB: {$e->getMessage()}",500);
			}
			else{
				show_error("Unable to connect to MongoDB",500);
			}

		}


	}

	/* Insert function
	 *
	 * Function can be used to insert data in Datebase
	 *
	 * @usage : $this->mongodb->insert('foo', ['key' => 'value']);
	*/

	public function insert( $collection = "", $data = array() ){

		if( empty($collection) ){
			show_error('No mongo collection selected to insert into',500);
		}

		if( !is_array($data) || empty($data) ){
			show_error("Nothing to insert into mongo collection or data is not an array",500);
		}

		try{

			$bulk = new MongoDB\Driver\bulkWrite();

			$data = array_reverse($data,true);
			$data['_id'] = new MongoDB\BSON\ObjectID;
			$data = array_reverse($data);

			$bulk->insert($data);

			$result = $this->connect->executeBulkWrite($this->db.'.'.$collection, $bulk);

			if(empty($result->getWriteErrors())){
				return (String)$data['_id'];
			}
			else{
				show_error("Insertion failed in mongoDB",500);
			}

		}
		catch( MongoCursorException $e ){

			if(isset($this->debug) == TRUE && $this->debug == TRUE){
				show_error("Insert of data into MongoDB failed: {$e->getMessage()}", 500);
			}
			else{
				show_error("Insert of data into MongoDB failed", 500);
			}

		}

	}

	/* Insert function
	 *
	 * Function can be used to insert batch data in Datebase
	 *
	 * @usage : $this->mongodb->insert('foo', [['key' => 'value'],['key' => 'value']]);
	*/

	public function bactch_insert($collection="",$data=array()){

		if( empty($collection) ){
			show_error('No mongo collection selected to insert into',500);
		}

		if( !is_array($data) || empty($data) ){
			show_error("Nothing to insert into mongo collection or data is not an array",500);
		}

		try{

			$bulk = new MongoDB\Driver\bulkWrite();

			$insert_arr = array();

			foreach ($data as $value) {
				if( !is_array($value) ){
					show_error("Invalid Inputs",500);die;
				}
				else{

					$temp = array_reverse($value,true);
					$temp['_id'] = new MongoDB\BSON\ObjectID;
					$temp = array_reverse($temp);

					$bulk->insert($temp);

					$insert_arr[] = (String)$temp['_id'];

				}
			}

			$result = $this->connect->executeBulkWrite($this->db.'.'.$collection, $bulk);

			if(empty($result->getWriteErrors())){
				return $insert_arr;
			}
			else{
				show_error("Insertion failed in mongoDB",500);
			}

		}
		catch( MongoCursorException $e ){

			if(isset($this->debug) == TRUE && $this->debug == TRUE){
				show_error("Insert of data into MongoDB failed: {$e->getMessage()}", 500);
			}
			else{
				show_error("Insert of data into MongoDB failed", 500);
			}

		}

	}

	/*
	 * This function can be used to set the specific column of which data you need
	 *
	 * @usage $this->mongodb->select(['foo','bar'])->get('foobar');
	*/

	public function select($columns=array()){

		if( !is_array($columns) ){
			show_error('Columns should be array',500);
		}
		else{

			if( empty($columns) ){
				$this->selects = [];
			}
			else{

				foreach ($columns as $column) {

					if( is_array($column) ){
						show_error('Column aruguments should contains column name only not array',500);
					}
					else{
						$this->selects[] = $column;
					}
				}

			}

			return $this;

		}

	}

	/*
	 * Get the mongo collection details or say documents The where array should be associative array
	 * Where key should be field and value will used for search
	 *
	 * @usage: $this->mongodb->where(['foo' => 'bar'])->get('foobar');
	*/

	public function where($where){

		if( is_array($where) ){

			if( array_keys($where) !== range(0, count($where) - 1) ){

				foreach ($where as $k => $w) {
					$this->where[$k] = $w;
				}

				return $this;
	    	}
	    	else{
	    		show_error('Condition should be given in associative array',500);
	    	}

		}
		else{
			show_error('Conditions should be associative array',500);
		}

	}

	/*
	 * This condition can be used as or where in sql 
	 * 
	 * @usage : $this->mongodb->or_where(['foo' => 'bar'])->get('foobar');
	 *
	*/

	public function or_where($where = array()){

		if( is_array($where) && !empty($where) ){

			if( !isset($this->where['$or']) || !is_array($this->where['$or']) ){
				$this->where['$or'] = [];
			}

			foreach ($where as $key => $value) {
				$this->where['$or'][] = [$key => $value];
			}
			return $this;

		}
		else{
			show_error('Argument in or_where should be array',500);
		}

	}

	/*
	 * Where in clause for mongo DB
	 * @usage : $this->mongdb->where_in('foo',['bar','zoo','blah'])->get('foobar');
	 *
	*/

	public function where_in($field="",$in = array()){

		if( empty($field) ){
			show_error("field is required to perform IN clause in query",500);
		}

		if( is_array($in) && ( count($in) > 0 ) ){
			$this->_w($field);

			$this->where[$field]['$in'] = $in;

			return $this;
		}

	}


	/*
	 * Where in ALL
	 * use of $all in mongo db
	 * Match the feilds with all fields
	 * @usage: $this->mongodb->where_in_all('foo',['foo','bar','asd'])->get('foobar');
	*/

	public function where_in_all($column = "", $in = array()){

		if( empty($column) ){
			show_error("Mongo column is require to perfoem where in all query",500);
		}
		elseif( is_array($in) && count($in) ){

			$this->_w($column);
			$this->where[$column]['$all'] = $in;
			// echo "<pre>"; print_r($this->where);die;
			return $this;

		}
		else{
			show_error('$in parameter should be array',500);
		}

	}

	/*
	 * Get the document where values of a $column is not in an array $in
	 *
	 * @usage: $this->mongodb->where_not_in('foo',['bar','zoo','blah'])->get('foobar');
	 *
	*/

	public function where_not_in($column="", $in = array()){

		if( empty($column) ){
			show_error('Column is require to perform where not in clause in query',500);
		}
		elseif( is_array($in) && count($in) ){
			$this->_w($column);
			$this->where[$column]['$nin'] = $in;

			return $this;
		}
		else{
			show_error('in value should be an array',500);
		}

	}

	/*
	 * This function can be used to find the document where the value is grater than given  
	 *
	 * @usage: $this->mongodb->where_gtr('foo',5);
	 *
	*/

	public function where_gtr($column = null,$num = null){

		if( empty($column) || ( $column == null ) ){
			show_error("Column is require to perform greter then query",500);
		}
		elseif( empty($num) || ( $num == null ) ){
			show_error('$num value is require to perform greter than query',500);
		}
		else{

			$this->_w($column);
			$this->where[$column]['$gt'] = $num;

		}

		return $this;

	}


	/*
	 * function can be used to get the document where the value of a $column is greater than or equal to $num 
	 * 
	 * @usage: $this->mongodb->where_gtr_eq('foo',20)->get('foobar');
	*/

	public function where_gtr_eq($column=null,$num=null){
		
		if( empty($column) || ( $column == null ) ){
			show_error('Column is require to perform greater than or equal to query',500);
		}
		elseif( empty($num) || ( $num == null ) ){
			show_error('$num field\'s value is require to perform this query',500);
		}
		else{
			$this->_w($column);
			$this->where[$column]['$gte'] = $num;
		}

		return $this;

	}

	/*
	 * function can be used to get the document where the value of a $column is less than $num 
	 * 
	 * @usage: $this->mongodb->where_lt('foo',20)->get('foobar');
	*/

	public function where_lt($column=null,$num=null){
		
		if( empty($column) || ( $column == null ) ){
			show_error('Column is require to perform greater than or equal to query',500);
		}
		elseif( empty($num) || ( $num == null ) ){
			show_error('$num field\'s value is require to perform this query',500);
		}
		else{
			$this->_w($column);
			$this->where[$column]['$lt'] = $num;
		}

		return $this;

	}

	/*
	 * function can be used to get the document where the value of a $column is less than or equal to $num 
	 * 
	 * @usage: $this->mongodb->where_lt_eq('foo',20)->get('foobar');
	*/

	public function where_lt_eq($column=null,$num=null){
		
		if( empty($column) || ( $column == null ) ){
			show_error('Column is require to perform greater than or equal to query',500);
		}
		elseif( empty($num) || ( $num == null ) ){
			show_error('$num field\'s value is require to perform this query',500);
		}
		else{
			$this->_w($column);
			$this->where[$column]['$lte'] = $num;
		}

		return $this;

	}

	/*  
     * Between clause 
     * 
     * Fetch documents whose values are between the given values
     *  
     * @usage: $this->mongodb->where_between('foo',20,40)->get('foobar');
	*/

	public function where_between($column = "", $num1=null, $num2=null){
		if( empty($column) || ( $column == null ) ){
			show_error('$column field is required in order to perform in between condition in query',500);
		}
		elseif( empty($num1) || ( $num1 == null ) ){
			show_error('$num1 field is required in order to perform in between condition in query',500);
		}
		elseif( empty($num2) || ( $num2 == null ) ){
			show_error('$num2 field is required in order to perform in between condition in query',500);
		}
		else{
			$this->_w($column);
			$this->where[$column]['$gte'] = $num1;
			$this->where[$column]['$lte'] = $num2;
		}

		return $this;
	}

	/*
	 * This function can be used to fetch documents whose values is between given $num1 and $num2 but not equal to either $num1 or $num2
	 *
	 * @usage: $this->mongodb->where_between_neq('foo',32,35)->get('foobar');
	*/

	public function where_between_neq($column = null,$num1 = null,$num2 = null){

		if( empty($column) || ( $column == null ) ){
			show_error('$column field is required in order to perform in between condition in query',500);
		}
		elseif( empty($num1) || ( $num1 == null ) ){
			show_error('$num1 field is required in order to perform in between condition in query',500);
		}
		elseif( empty($num2) || ( $num2 == null ) ){
			show_error('$num2 field is required in order to perform in between condition in query',500);
		}
		else{
			$this->_w($column);
			$this->where[$column]['$gt'] = $num1;
			$this->where[$column]['$lt'] = $num2;
		}

		return $this;

	}

	/*
	 * This function can be used to fetch documents whose values is not equal to given
	 *
	 * @usage: $this->mongodb->where_between_neq('foo',32,35)->get('foobar');
	*/

	public function where_neq($column = null,$num1 = null){

		if( empty($column) || ( $column == null ) ){
			show_error('$column field is required in order to perform in between condition in query',500);
		}
		elseif( empty($num1) || ( $num1 == null ) ){
			show_error('$num1 field is required in order to perform in between condition in query',500);
		}
		else{
			$this->_w($column);
			$this->where[$column]['$ne'] = $num1;
		}

		return $this;

	}

	/**
	 * LIKE condition 
	 *
	 * Fetch documents where the value ( string ) of a given column is like a value. 
	 *
	 * default is Case-insensitive.
	 * 
	 * @param $flags
	 * 
	 * Allow for the typical regular expression flags
	 * 
	 * i = case insensitive
	 * m = multiline
	 * x = can contain comments
	 * l = locale
	 * s= dotall, "." matches everything, includings newlines
	 *
     * @param $start_wildcard
     *
     * If set to anything other than TRUE, a starting line character "^" will be prepended
     * to the search value, representing only searching for a valueat the start of a new line
     * 
     * @param $end_wildcard
     * 
     * If set to anything other than TRUE, an ending line character "$" will be appended to the search value representing
     * only searching for a value at the end of a line.
     *
     * @usage : $this->mongodb->like('foo','bar','i',false)->get('foobar');
     *
	*/

	public function like($column = null, $value = "",$flag="i",$start_wildcard = true,$end_wildcard = true){

		if( empty($column) || ( $column == null ) ){
        	show_error("Column is require to perform like query",500);
		}
		elseif( empty($value) || ( $value == null ) ){
			show_error("field's value is required to perform like query",500);
		}

		$column = (String)trim($column);
		$this->_w($column);

		$value = (String)trim($value);

		$value = quotemeta($value);

		if( $start_wildcard !== true ){

			$value = "^". $value;
		
		}
		if( $end_wildcard !== true ){

			$value .= "$";

		}

		$this->where[$column] = new MongoDB\BSON\Regex($value,$flag);

		return $this;

	}

	/*
	 * Get the Documents based upon the parameters
	 *
	 * @usage: $this->mongodb->get('foobar');
	 *
	*/

	public function get($collection = ""){

		if( empty($collection) ){
			show_error('In order to retrieve document from MongoDB, a collection name must be passed',500);
		}
		else{

			$query = new MongoDB\Driver\Query($this->where, ['limit' => $this->limit]);
			// echo "<pre>"; print_r($query);die;
			$readPreference = new MongoDB\Driver\ReadPreference(MongoDB\Driver\ReadPreference::RP_PRIMARY);
			$cursor = $this->connect->executeQuery($this->db.'.'.$collection, $query, $readPreference);
			
			return $cursor;
		}

	}

	/**
	 * this function is used to fetch document as direct with where condition
	 * 
	 * @usage: $this->mongodb->get_where('foobar',['foo' => 'bar']);
	 * 
	 * say shorthand of $this->mongodb->where('foo',['foo'=>'bar'])->get('foobar'); 
	 *
	*/

	public function get_where($collection = null, $where = array()){

		if( empty($collection) || ($collection == null ) ){
			show_error('Collection name is required to perform this query',500);
		}
		elseif( !is_array($where) || empty($where) || ( $where == null ) ){
			show_error('Valid where condition is required inorder to perform this query');
		}
		else{

			foreach ($where as $key => $value) {

				$this->where[$key] = $value;
			
			}

			return $this->get($collection);
		}
	}

	/**
	 * Fetch the single document based on given parameters
	 * 
	 * @usage: $this->mongodb->findOne('foo',"asdasdasdads3453434rfw");
	*/

	public function findOne($collection = "", $id = null){

		if( empty($collection) || ($collection == null) ){
			show_error('$collection name should be provided to perform this query',500);
		}
		elseif( is_array($id) || empty($id) || ( $id == null ) ){
			show_error("Valid Primary key is required in order to  perform this query",500);
		}
		else{

			try{

				$this->where["_id"] = new MongoDB\BSON\ObjectID($id);

				return $this->get($collection);

			}
			catch( MongoCursorException $e ){

				if( isset($this->debug) && $this->debug ){
					show_error("mongoDB query failed: {$e->getMessage()}",500);
				}
				else{
					show_error("mongoDB query failed",500);
				}

			}

		}

	}

	/**
	 * Count the document based upon params
	 * 
	 * @usage: $this->mongodb->count('foobar');
	 * 
	 * or you can also use it with other conditions as well
	 *
	*/

	public function count($collection = null){
		
		if( empty($collection) || ( $collection == null ) ){

			show_error('$collection name is required to count document');

		}
		else{

			$result = $this->get($collection);

			return count(iterator_to_array($result));

		}

	}	

	/**
	 * Sets a value to a field
	 *
	 * @usage: $this->mongdb->where(['foo' => 'bar'])->set(['bar'=>'34'])->update('foobar');
	 *
	*/

	public function set($data = [] ){

		if( !is_array($data) || empty($data) || ( $data == null ) ){

			show_error('$data should be array containing col=>val is required in set method',500);

		}
		else{

			if( !isset($this->update['$set']) ){
				$this->update['$set'] = [];
			}

			foreach ($data as $col => $val) {
				$this->update['$set'][$col] = $val;
			}
			
			return $this;

		}


	}

	/**
	 * This function can be used to unset a fields
	 *
	 * @usage: $this->mongodb->where(['foo' => 'bar'])->unset(['asd','cdc'])->update('foobar');
	 *
	*/
	
	public function unset($columns = []){

		if( !is_array($columns) || empty($columns) || ( $columns == null ) ){
 			show_error('$column should be array with valid column name in order to unset',500);
		}
		else{

			if( !isset($this->update['$unset']) ){
				$this->update['$unset'] = [];
			}

			foreach ($columns as $column) {
				$this->update['$unset'][$column] = 1;
			}

			return $this;

		}

	}

	/**
	* This function can be used to add column in collection
	* 
	* This function takes argument as array contains keys as  field name and value as field value
	*   
	* @usage: $this->mongodb->where(['foo' => 'bar'])->add_to_set(['created_at' => 123,'updated_at' => 123123])->update('foobar');
	*
	*/

	public function add_to_set($columns_val=[]){

		if( !is_array($columns_val) || empty($columns_val) || ($columns_val == null ) ){
			show_error("Please specify the column and its value in order to alter  the table",500);
		}
		else{

			if( ! isset($this->update['$addToSet']) ){
				$this->update['$addToSet'] = [];
			}

			foreach ($columns_val as $col => $val) {
				
				if( !is_array($val) ){
					$this->update['$addToSet'][$col] = $val;
				}
				else{
					$this->update['$addToSet'][$col] = ['$each' => $val];
				}

			}

			return $this;

		}


	}

	/**
     * This function can be used to to rename field of mongoDB collection
     * 
     * @usage: $this->mongodb->where(['foo' => 'bar'])->rename_col($oldColName, $newColName)->update('foobar');
     * 
	*/
	public function rename_col($oldColName,$newColName){

		if( !is_string($oldColName) || !is_string($newColName) || empty($oldColName) || empty($newColName) || in_array(null, [$newColName,$oldColName]) ){
			show_error('Old and New column name is required in order to rename column',500);
		}
		else{

			if( !isset($this->update['$rename']) ){
				$this->update['$rename'] = [];
			}

			$this->update['$rename'] = [$oldColName => $newColName];

			return $this;
		}

	}

	/**
     * This function can be used to increment value of field of mongoDB collection
     * Field value should be numeric in order to increment it
     * @usage: $this->mongodb->where(['foo' => 'bar'])->inc(['comments_count' => 1])->update('foobar');
     * 
	*/
	public function inc($data = []){

		if( !is_array($data) || empty($data) || ( $data == null ) ){
			show_error('column name and value is required in order to increment value',500);
		}
		else{

			if( !isset($this->update['$inc']) ){
				$this->update['$inc'] = [];
			}

			foreach ($data as $col => $val) {
				$this->update['$inc'][$col] = $val;				
			}

			return $this;
		}

	}	


	/**
     * This function can be used to multiply given value with existing column value
     * 
     * @usage: $this->mongodb->where(['foo' => 'bar'])->multiply(['created_at' => 5])->update('foobar');
     * 
	*/
	public function multiply($data = []){

		if( !is_array($data) || empty($data) || ( $data == null ) ){
			show_error('column name and value is required in order to increment value',500);
		}
		else{

			if( !isset($this->update['$mul']) ){
				$this->update['$mul'] = [];
			}

			foreach ($data as $col => $val) {
				$this->update['$mul'][$col] = $val;				
			}

			return $this;
		}

	}

	/**
     * This function can be used to delete collection
     * 
     * @usage: $this->mongodb->where(['foo' => 'bar'])->delete('foobar');
     * 
	*/
	public function delete($collection = ""){

		if( !is_string($collection) || empty($collection) || ( $collection == null ) ){
			show_error('collection name is required in order to delete',500);
		}
		else{

			try{

				$bulk = new MongoDB\Driver\BulkWrite();

				$bulk->delete($this->where);

				$result = $this->connect->executeBulkWrite($this->db.'.'.$collection, $bulk);
				
				$error = $result->getWriteErrors();

				if(empty($error)){
					return true;	
				}
				else{
					show_error("Deletion failed.",500);
				}

			}
			catch( MongoCursorException $e ){

				if( isset($this->debug) && $this->debug ){
					show_error("Delete data from mongodb's collection failed: {$e->getMessage()}",500);
				}
				else{
					show_error("Delete data from mongoDB's collection is failed.",500);
				}

			}
		}

	}


	/**
     * This function can be used to set the limit 
     * 
     * $this->mongodb->limit(3)->get('foobar');
	*/

	public function limit($num = 999999){

		if( !is_numeric($num) || ($num == null) || ( $num < 1 ) ){
			show_error('Limit should be numeric value greater than 1 and not null',500);
		}

		$this->limit = (int)$num;

		return $this;
	}

	/**
	 * This function can be used to update the collection
	 *
	 * @usage: $this->mongodb->where(['foo' => 'bar'])->set(['foo' => 'rab'])->update('foobar');
	 *
	*/

	public function update($collection = null){

		if( empty($collection) | ($collection == null) ){
			show_error('Collection name is required in order to run update query',500);
		}
		else{

			try{

				$bulk = new MongoDB\Driver\BulkWrite();

				$bulk->update($this->where, $this->update,['multi' => false, 'upsert' => false]);

				// echo "<pre>"; print_r($bulk);die;

				$result = $this->connect->executeBulkWrite($this->db.'.'.$collection, $bulk);
				
				$error = $result->getWriteErrors();

				if(empty($error)){
					return true;	
				}
				else{
					show_error("Updation failed.",500);
				}

			}
			catch( MongoCursorException $e ){

				if( isset($this->debug) && $this->debug ){
					show_error("Update data into mongodb's collection failed: {$e->getMessage()}",500);
				}
				else{
					show_error("Update of data into mongoDB's collection is failed.",500);
				}

			}

		}

	}


	/**
	* --------------------------------------------------------------------------------
	* Where initializer
	* --------------------------------------------------------------------------------
	*
	* Prepares parameters for insertion in $wheres array().
	*/
	private function _w($param)
	{
		if ( ! isset($this->where[$param]))
		{
			$this->where[$param] = array();
		}
	}
}