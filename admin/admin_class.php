<?php
session_start();
ini_set('display_errors', 1);
Class Action {
	private $db;

	public function __construct() {
		ob_start();
   	include 'db_connect.php';
    
    $this->db = $conn;
	}
	function __destruct() {
	    $this->db->close();
	    ob_end_flush();
	}

	function login(){
		
			extract($_POST);	
			$qry = $this->db->prepare("SELECT * FROM users where username = ?");
			$qry->bind_param('s', $username);
			$qry->execute();

			$result = $qry->get_result();

			if($result->num_rows > 0){
				$userData = $result->fetch_assoc();
				$user_password = $userData['password'];
				$user_username = $userData['username'];
				$type = $userData['type'];
				$name = $userData['name'];
				$user_id = $userData['id'];

				if (!$this->validateAccessAttemp($user_id)) {
					return 4;
				}

				if(!password_verify($password, $user_password)) {
					$date = date('Y-m-d H:i:s');
					$this->db->query("INSERT INTO access_attemp(user_id, date, status) values($user_id, '$date', 0)");
					return 3;
				}

				$_SESSION['login_username'] = $user_username;
				$_SESSION['login_name'] = $name;
				$_SESSION['login_type'] = $type;
				$_SESSION['login_id'] = $user_id;
				
				if($_SESSION['login_type'] != 1){
					foreach ($_SESSION as $key => $value) {
						unset($_SESSION[$key]);
					}
					return 2 ;
					exit;
				}
					return 1;
			}else{
				return 3;
			}
	}

	function validateAccessAttemp($userId) {
		$to_date = date('Y-m-d H:i:s');
		$from_date = date('Y-m-d H:i:s', strtotime('-60 minutes'));

		$qry = $this->db->prepare("select count(*) total from access_attemp where user_id = ? and status = 0 and date between ? and ? ");
		$qry->bind_param('iss', $userId, $from_date, $to_date);
		$qry->execute();
		$result = $qry->get_result();
		if($result->num_rows > 0){
			$data = $result->fetch_assoc();
			if ($data['total'] > 10) {
				return false;
			}
		}
		return true;
	}

	function login2(){
		
			extract($_POST);
			if(isset($email))
				$username = $email;
		$qry = $this->db->query("SELECT * FROM users where username = '".$username."' and password = '".password_hash($password, PASSWORD_BCRYPT)."' ");
		if($qry->num_rows > 0){
			foreach ($qry->fetch_array() as $key => $value) {
				if($key != 'passwors' && !is_numeric($key))
					$_SESSION['login_'.$key] = $value;
			}
			if($_SESSION['login_alumnus_id'] > 0){
				$bio = $this->db->query("SELECT * FROM alumnus_bio where id = ".$_SESSION['login_alumnus_id']);
				if($bio->num_rows > 0){
					foreach ($bio->fetch_array() as $key => $value) {
						if($key != 'passwors' && !is_numeric($key))
							$_SESSION['bio'][$key] = $value;
					}
				}
			}
			if($_SESSION['bio']['status'] != 1){
					foreach ($_SESSION as $key => $value) {
						unset($_SESSION[$key]);
					}
					return 2 ;
					exit;
				}
				return 1;
		}else{
			return 3;
		}
	}
	function logout(){
		session_destroy();
		foreach ($_SESSION as $key => $value) {
			unset($_SESSION[$key]);
		}
		header("location:login.php");
	}
	function logout2(){
		session_destroy();
		foreach ($_SESSION as $key => $value) {
			unset($_SESSION[$key]);
		}
		header("location:../index.php");
	}

	function save_user(){

		$password_regex = "/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$/";
		extract($_POST);
		$data = " name = '$name' ";
		$data .= ", username = '$username' ";
		if(!empty($password)) {
			if(preg_match($password_regex, $password) == 0) {
				return 33;
			}
			$data .= ", password = '".password_hash($password, PASSWORD_BCRYPT)."' ";
		}
		$data .= ", type = '$type' ";
		
		$chk = $this->db->query("Select * from users where username = '$username' and id !='$id' ")->num_rows;
		if($chk > 0){
			return 2;
			exit;
		}
		if(empty($id)){
			$save = $this->db->query("INSERT INTO users set ".$data);
		}else{
			$save = $this->db->query("UPDATE users set ".$data." where id = ".$id);
		}
		if($save){
			return 1;
		}
	}
	function delete_user(){
		extract($_POST);
		$delete = $this->db->query("DELETE FROM users where id = ".$id);
		if($delete)
			return 1;
	}
	function signup(){
		extract($_POST);
		$data = " name = '".$firstname.' '.$lastname."' ";
		$data .= ", username = '$email' ";
		$data .= ", password = '".password_hash($password, PASSWORD_BCRYPT)."' ";
		$chk = $this->db->query("SELECT * FROM users where username = '$email' ")->num_rows;
		if($chk > 0){
			return 2;
			exit;
		}
			$save = $this->db->query("INSERT INTO users set ".$data);
		if($save){
			$uid = $this->db->insert_id;
			$data = '';
			foreach($_POST as $k => $v){
				if($k =='password')
					continue;
				if(empty($data) && !is_numeric($k) )
					$data = " $k = '$v' ";
				else
					$data .= ", $k = '$v' ";
			}
			if($_FILES['img']['tmp_name'] != ''){
							$fname = strtotime(date('y-m-d H:i')).'_'.$_FILES['img']['name'];
							$move = move_uploaded_file($_FILES['img']['tmp_name'],'assets/uploads/'. $fname);
							$data .= ", avatar = '$fname' ";

			}
			$save_alumni = $this->db->query("INSERT INTO alumnus_bio set $data ");
			if($data){
				$aid = $this->db->insert_id;
				$this->db->query("UPDATE users set alumnus_id = $aid where id = $uid ");
				$login = $this->login2();
				if($login)
				return 1;
			}
		}
	}
	function update_account(){
		extract($_POST);
		$data = " name = '".$firstname.' '.$lastname."' ";
		$data .= ", username = '$email' ";
		if(!empty($password))
		$data .= ", password = '".password_hash($password, PASSWORD_BCRYPT)."' ";
		$chk = $this->db->query("SELECT * FROM users where username = '$email' and id != '{$_SESSION['login_id']}' ")->num_rows;
		if($chk > 0){
			return 2;
			exit;
		}
			$save = $this->db->query("UPDATE users set $data where id = '{$_SESSION['login_id']}' ");
		if($save){
			$data = '';
			foreach($_POST as $k => $v){
				if($k =='password')
					continue;
				if(empty($data) && !is_numeric($k) )
					$data = " $k = '$v' ";
				else
					$data .= ", $k = '$v' ";
			}
			if($_FILES['img']['tmp_name'] != ''){
							$fname = strtotime(date('y-m-d H:i')).'_'.$_FILES['img']['name'];
							$move = move_uploaded_file($_FILES['img']['tmp_name'],'assets/uploads/'. $fname);
							$data .= ", avatar = '$fname' ";

			}
			$save_alumni = $this->db->query("UPDATE alumnus_bio set $data where id = '{$_SESSION['bio']['id']}' ");
			if($data){
				foreach ($_SESSION as $key => $value) {
					unset($_SESSION[$key]);
				}
				$login = $this->login2();
				if($login)
				return 1;
			}
		}
	}

	function save_settings(){
		extract($_POST);
		$data = " name = '".str_replace("'","&#x2019;",$name)."' ";
		$data .= ", email = '$email' ";
		$data .= ", contact = '$contact' ";
		$data .= ", about_content = '".htmlentities(str_replace("'","&#x2019;",$about))."' ";
		if($_FILES['img']['tmp_name'] != ''){
						$fname = strtotime(date('y-m-d H:i')).'_'.$_FILES['img']['name'];
						$move = move_uploaded_file($_FILES['img']['tmp_name'],'assets/uploads/'. $fname);
					$data .= ", cover_img = '$fname' ";

		}
		
		// echo "INSERT INTO system_settings set ".$data;
		$chk = $this->db->query("SELECT * FROM system_settings");
		if($chk->num_rows > 0){
			$save = $this->db->query("UPDATE system_settings set ".$data);
		}else{
			$save = $this->db->query("INSERT INTO system_settings set ".$data);
		}
		if($save){
		$query = $this->db->query("SELECT * FROM system_settings limit 1")->fetch_array();
		foreach ($query as $key => $value) {
			if(!is_numeric($key))
				$_SESSION['system'][$key] = $value;
		}

			return 1;
				}
	}

	
	function save_category(){
		extract($_POST);
		$data = " name = '$name' ";
		$data .= ", description = '$description' ";
			if(empty($id)){
				$save = $this->db->query("INSERT INTO categories set $data");
			}else{
				$save = $this->db->query("UPDATE categories set $data where id = $id");
			}
		if($save)
			return 1;
	}
	function delete_category(){
		extract($_POST);
		$delete = $this->db->query("DELETE FROM categories where id = ".$id);
		if($delete){
			return 1;
		}
	}
	function save_transmission(){
		extract($_POST);
		$data = " name = '$name' ";
		$data .= ", description = '$description' ";
			if(empty($id)){
				$save = $this->db->query("INSERT INTO transmission_types set $data");
			}else{
				$save = $this->db->query("UPDATE transmission_types set $data where id = $id");
			}
		if($save)
			return 1;
	}
	function delete_transmission(){
		extract($_POST);
		$delete = $this->db->query("DELETE FROM transmission_types where id = ".$id);
		if($delete){
			return 1;
		}
	}
	function save_engine(){
		extract($_POST);

		if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
			// return 405 http status code
			header($_SERVER['SERVER_PROTOCOL'] . ' 405 Method Not Allowed');
			exit;
		} else {
			$data = " name = '$name' ";
			if(empty($id)){
				$save = $this->db->query("INSERT INTO engine_types set $data");
			}else{
				$save = $this->db->query("UPDATE engine_types set $data where id = $id");
			}
		if($save)
			return 1;
		}
	}
	function delete_engine(){
		extract($_POST);
		$delete = $this->db->query("DELETE FROM engine_types where id = ".$id);
		if($delete){
			return 1;
		}
	}
	function save_car(){
		extract($_POST);
		$data = "";
		foreach($_POST as $k => $v){
			if(!in_array($k, array('id','img','description')) && !is_numeric($k)){
				$value = htmlspecialchars($v);
				if(empty($data)){
					$data .= " $k='$value' ";
				}else{
					$data .= ", $k='$value' ";
				}
			}
		}
		$data .= ", description = '".htmlentities(str_replace("'","&#x2019;",$description))."' ";
		if($_FILES['img']['tmp_name'] != ''){
						$fname = strtotime(date('y-m-d H:i')).'_'.$_FILES['img']['name'];
						$fname = str_replace(" ", '', $fname);
						$move = move_uploaded_file($_FILES['img']['tmp_name'],'assets/uploads/cars_img/'. $fname);
					$data .= ", img_path = '$fname' ";
		}
		if(empty($id)){
			$save = $this->db->query("INSERT INTO cars set $data");
		}else{
			$save = $this->db->query("UPDATE cars set $data where id = $id");
		}

		if($save)
			return 1;
	}
	function delete_car(){
		extract($_POST);
		$delete = $this->db->query("DELETE FROM cars where id = ".$id);
		if($delete){
			return 1;
		}
	}
	function save_book(){
		extract($_POST);
		$data = "";
		foreach($_POST as $k => $v){
			if(!in_array($k, array('id')) && !is_numeric($k)){
				if(empty($data)){
					$data .= " $k='$v' ";
				}else{
					$data .= ", $k='$v' ";
				}
			}
		}

		if(empty($id)){
			$save = $this->db->query("INSERT INTO books set ".$data);
		}else{
			$save = $this->db->query("UPDATE books set ".$data." where id=".$id);
		}
		if($save)
			return 1;
	}
	function delete_book(){
		extract($_POST);
		$delete = $this->db->query("DELETE FROM books where id = ".$id);
		if($delete){
			return 1;
		}
	}
	function get_booked_details(){
		extract($_POST);
		$qry = $this->db->query("SELECT b.*,c.brand, c.model FROM books b inner join cars c on c.id = b.car_id where b.id = $id ")->fetch_array();
		$data = array();
		foreach($qry as $k=>$v){
			if(!is_numeric($k))
			$data[$k]= $v;
		}
			return json_encode($data);
	}
	function save_movement(){
		extract($_POST);
		$data = " booked_id = '$book_id' ";
		$data .= ", car_id = '$car_id' ";

		if(empty($id)){
			$save = $this->db->query("INSERT INTO borrowed_cars set ".$data);
			if($save){
				$data = " car_registration_no = '$car_registration_no' ";
				$data .= ", car_plate_no = '$car_plate_no' ";
				$this->db->query("UPDATE books set $data where id = $book_id");
			}
		}else{
		$data .= ", status = '$status' ";
			$save = $this->db->query("UPDATE borrowed_cars set ".$data." where id=".$id);
		}
		if($save)
			return 1;
	}
	function delete_movement(){
		extract($_POST);
		$delete = $this->db->query("DELETE FROM borrowed_cars where id = ".$id);
		if($delete){
			return 1;
		}
	}
	function save_event(){
		extract($_POST);
		$data = " title = '$title' ";
		$data .= ", schedule = '$schedule' ";
		$data .= ", content = '".htmlentities(str_replace("'","&#x2019;",$content))."' ";
		if($_FILES['banner']['tmp_name'] != ''){
						$_FILES['banner']['name'] = str_replace(array("(",")"," "), '', $_FILES['banner']['name']);
						$fname = strtotime(date('y-m-d H:i')).'_'.$_FILES['banner']['name'];
						$move = move_uploaded_file($_FILES['banner']['tmp_name'],'assets/uploads/'. $fname);
					$data .= ", banner = '$fname' ";

		}
		if(empty($id)){

			$save = $this->db->query("INSERT INTO events set ".$data);
		}else{
			$save = $this->db->query("UPDATE events set ".$data." where id=".$id);
		}
		if($save)
			return 1;
	}
	function delete_event(){
		extract($_POST);
		$delete = $this->db->query("DELETE FROM events where id = ".$id);
		if($delete){
			return 1;
		}
	}
	
	function participate(){
		extract($_POST);
		$data = " event_id = '$event_id' ";
		$data .= ", user_id = '{$_SESSION['login_id']}' ";
		$commit = $this->db->query("INSERT INTO event_commits set $data ");
		if($commit)
			return 1;

	}
}