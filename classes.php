<?php
class Tempel
{
	var $content = '';
	
	//--------------------SITE-config------------------
	var $absolute_path = 'http://cards.portorens.com';
	var $cookie_name = 'cards.portorens.com';
	//-------------------------------------------------
	
	//--------------------DB-config--------------------
	var $sh = 'c_';
	var $db_name = 'cardsportorens';
	var $db_password = 'LUDGHLUGDew9ffglwefkug';
	var $db_login = 'cardsportorens';
	//-------------------------------------------------
	
	
	
	var $request = '';
	var $request_mass = '';
	var $mysqli;
	var $type;
	
	var $message_info = '';
	var $message_alert = '';
	var $message_error = '';
	var $message_success = '';
	var $sql;
	var $page_data;
	
	var $title = '';
	var $description = '';
	var $keywords = '';
	var $h1 = '';
	
	var $main_content;
	var $messeges;
	var $error;
	var $footer;
	
	
	
	function __construct($type)
	{
		$this->type = $type;
		if ($this->type=='main') {
			$this->sql_init();
			
			$this->request();
			$this->generate_page();
			
			
		} elseif ($this->type=='API') {
			//заготовка для будущего возможного использования API
			$this->sql_init();
			
		}
	}
	
	//проводим финальную компановку всей страницы
	function __destruct()
	{
		if ($this->type=='main') {
			
			if (!isset($this->text)) $this->text = '';
			$this->content = str_replace('{{contents}}', $this->snippet_compile($this->text), $this->content);
			
			$this->content = str_replace('{{message_info}}', $this->message_info(), $this->content);
			$this->content = str_replace('{{message_alert}}', $this->message_alert(), $this->content);
			$this->content = str_replace('{{message_error}}', $this->message_error(), $this->content);
			$this->content = str_replace('{{message_success}}', $this->message_success(), $this->content);
			
			$this->content = str_replace('{{title}}', $this->title, $this->content);
			$this->content = str_replace('{{description}}', $this->description, $this->content);
			$this->content = str_replace('{{keywords}}', $this->keywords, $this->content);
			$this->content = str_replace('{{h1}}', $this->h1, $this->content);
		
			
			$this->link_compile();
			$this->content = str_replace('{{absolute_path}}', $this->absolute_path, $this->content);
			
			die($this->content);
			
		}
	}
	
	//возможность логирования действий
	function to_log ($name, $id, $data)
	{
		if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
		else $ip = 'none';
		
		$this->mysqli->query('INSERT INTO `'.$this->sh.'log` 
							(
							`date`,
							`data`,
							`name`,
							`user_id`,
							`ip`
							 ) 
						VALUES (
						NOW(),
						"'.$this->mysqli->real_escape_string($data).'",  
						"'.$this->mysqli->real_escape_string($name).'",  
						"'.$this->mysqli->real_escape_string($id).'",  
						"'.$this->mysqli->real_escape_string($ip).'"
						 ) '); 
	}
	

	//собираем все страницы
	function generate_page()
	{
		
		$row = $this->mysqli->query('SELECT * FROM `'.$this->sh.'contents` WHERE `alias` = "'.$this->request.'" ORDER BY `id` ASC LIMIT 1');
		$rows = $row->num_rows;
		if ($rows==0) {
			$row = $this->mysqli->query('SELECT * FROM `'.$this->sh.'contents` WHERE `alias` = "404"');
			$rows = $row->num_rows;
			
			header('HTTP/1.0 404 not found');
			header('Status: 404 Not Found');
		}
		if ($rows==1) {
			$result = $row->fetch_assoc();
			
			
			$this->page_data = $result;
			$this->title = $result['title'];
			$this->description = $result['description'];
			$this->keywords = $result['keywords'];
			$this->h1 = $result['h1'];
			$this->h2 = $result['h2'];
			$this->h3 = $result['h3'];
			$this->h4 = $result['h4'];
			$this->h5 = $result['h5'];
			$this->text = $result['snippets'].$result['text'];
			
			$this->mysqli->query('UPDATE `'.$this->sh.'contents` SET `view` = `view`+1 WHERE `alias` = "'.$this->request.'" LIMIT 1');
			
			$this->snippet();
			
			preg_match_all('|\[\[-(.*?)-\]\]|', $this->content, $result);
			
			if (isset($result[1])) {
				foreach($result[1] as $key) {
					if (isset($this->page_data[$key])) {
						$this->content = str_replace('[[-'.$key.'-]]', $this->page_data[$key], $this->content);
					}
				}
			}
			
		}
	}
	
	//собираем все найденные сниппеты в один общий массив
	function snippet()
	{
		$this->content = file_get_contents(dirname(__FILE__).'/../mvc/template/'.$this->page_data['template'].'.tpl');
		
		$row = $this->mysqli->query('SELECT * FROM `'.$this->sh.'snippet` ');
		$stop = 0;
		
		while($result = $row->fetch_assoc()) {
			$this->snippet_mass[$result['alias']] = $result;
		}
		
		$this->content = $this->snippet_compile($this->content);
	}
	
	//все шаблоны ссылок заменяем на реальные
	function link_compile()
	{
		preg_match_all('|\[~(.*?)~\]|', $this->content, $result);
			$SQL = '';
			
			if (isset($result[1]) && count($result[1])>0) {
				unset($temper_mass);
				foreach($result[1] as $key){
					if ($SQL=='') $SQL = ' `id` = "'.$this->mysqli->real_escape_string($key).'"';
					else $SQL .= ' or `id` = "'.$this->mysqli->real_escape_string($key).'"';
				}
				
				
				$row = $this->mysqli->query('SELECT `id`, `alias` FROM `'.$this->sh.'contents` WHERE'.$SQL);
				while($result = $row->fetch_assoc()) {
					$this->snippet_mass[$result['alias']] = $result;
					$this->content = str_replace('[~'.$result['id'].'~]', '{{absolute_path}}/'.$result['alias'], $this->content);
				}
			}
	}
	
	//проводим на странице поиск всех снипетов
	function snippet_compile($text)
	{
		preg_match_all('|{{(.*?)}}|', $text, $result);
		
		if (isset($result[1])) {
		
			unset($temper_mass);
			foreach($result[1] as $key){
				if (!isset($temper_mass[$key]) && isset($this->snippet_mass[$key])) {
					
					$expl = explode('.', $this->snippet_mass[$key]['file']);
					if ($expl[1]=='php') {
						if (is_file(dirname(__FILE__).'/../mvc/'.$this->snippet_mass[$key]['file'])) include(dirname(__FILE__).'/../mvc/'.$this->snippet_mass[$key]['file']);
						if (!isset($temper_data)) $temper_data = '';
					}
					else {
						if (is_file(dirname(__FILE__).'/../mvc/'.$this->snippet_mass[$key]['file'])) $temper_data = file_get_contents(dirname(__FILE__).'/../mvc/'.$this->snippet_mass[$key]['file']);
						else $temper_data = '';
						
						//проверяем есть ли динамический обработчик
						$expl_find = explode('/', $expl[0]);
						if ($expl_find[0]=='static' && is_file(dirname(__FILE__).'/../mvc/dynamic/'.$expl_find[1].'.php') ) {
							include(dirname(__FILE__).'/../mvc/dynamic/'.$expl_find[1].'.php');
						}
					}
					
					$temper_data = $this->snippet_compile($temper_data);
					
					$text = str_replace('{{'.$key.'}}', $temper_data, $text);
					$temper_mass[$key] = 1;
				}
			}
		}
		
		return $text;
	}
	
	//обработчик запросов
	function request()
	{
		if (isset($_GET['q'])) $this->htaccess($_GET['q']);
	}
	
	function htaccess($data)
	{
		$result = explode('/',$data);
		$this->request = $data;
		$this->request_mass = $result;
		
		return true;
	}
	
	//подключение к базе данных
	function sql_init()
	{
		require(dirname(__FILE__).'/bd.php');

	}
	
	function session()
	{
		
	}
	
	//генерация таблиц с поиском, пагинацией
	function admin_vkladka($nazv_tabl, $adr, $massfiles, $dopSQL, $dopWHERE, $sortirovka)
	{
		if (isset($_GET['search'])) {
			$search = '';
			foreach ( $_GET['search'] as $search_expl_key=>$search_expl ) {
				$search .= '&search['.$search_expl_key.']='.$search_expl;
			}
		} else $search = '';
		
		
		if (!isset($_POST['blockButton'])) $_POST['blockButton'] = '';
		if (!isset($_POST['from'])) $_POST['from'] = '';
		if (!isset($_POST['deleteButton'])) $_POST['deleteButton'] = '';
		if (!isset($massfiles['pagin_cols'])) $massfiles['pagin_cols'] = 10;
		if (!isset($_GET['page']) or $_GET['page'] == '') $_GET['page'] = 1;
		if (!isset($massfiles['onpage'])) $massfiles['onpage'] = 10;
		if (!isset($_GET['do']) or $_GET['do']=='') $_GET['do'] = 'index';
		
			$stolbhead = '';
			$stolbbody = '';
			
			foreach($sortirovka as $key) {
				$stolbhead .= '<th>'.$key['stolb_name'].'</th>';
				
			}
	
			$SQL = '';
			if (isset($dopWHERE) && $dopWHERE!='') {
				$SQL .= ' WHERE '.$dopWHERE;
			}
			if (isset($dopSQL) && $dopSQL!='') {
				$SQL .= ' '.$dopSQL;
			}
		
		
		$rowall = $this->mysqli->query('SELECT  COUNT(1) AS nums FROM `'.$nazv_tabl.'` '.$SQL) or $this->message_error = $this->mysqli->error;
		$countcels_buf = $rowall->fetch_assoc();
		$countcels = $countcels_buf['nums'];
		$pagination = '';
		//--------------------------
			$colP = ceil($countcels/$massfiles['onpage']);
		
		
		//определяем с какой страницы начать пагинацию 
			$pred_pagin = ceil($massfiles['pagin_cols']/2);
			if ($_GET['page']>$pred_pagin) $increment = $_GET['page']-$pred_pagin; else $increment = 1;
		
			$increment_buf = 1;
			for($increment; $increment<=$colP; $increment++) {
				
				if (isset($_GET['page']) && $increment == $_GET['page']) $pagination = $pagination.'<li class="active"><a href="#">'.$increment.'</a></li>';
				else $pagination = $pagination.'<li><a href="'.$this->admin_path.'?do='.$massfiles['adr'].'&page='.$increment.$search.'">'.$increment.'</a></li>';
				
				if ($increment_buf>=$massfiles['pagin_cols']) $increment = $countcels;
				$increment_buf++;
			}
			
		//--------------------------
		
		
		if ($_GET['page'] == 1) {
			$start = 0;			
		} else {
			$start = ($_GET['page']	* $massfiles['onpage']) - $massfiles['onpage'];		
		}
		
		$SQL_LIMIT = ' LIMIT '.$start.','.$massfiles['onpage'].' ';
		$SQL = str_replace('user_info[id]', $this->user_info['id'], $SQL);
		$SQL = str_replace('user_info[login]', $this->user_info['login'], $SQL);
		
		$row = $this->mysqli->query('SELECT * FROM `'.$nazv_tabl.'` '.$SQL.$SQL_LIMIT) or $this->message_error = $this->mysqli->error;
		if ($row->num_rows>0) {
			$increment = 1;
			$stolbbody = '';
			while($result = $row->fetch_assoc()) {
				
				$stolbbodybuf = '';
				foreach($sortirovka as $key) {
					if(isset($key['onclick']) and $key['onclick']!='') $onclick = ' onclick="'.$key['onclick'].'" ';
					else $onclick = '';
					
					if(isset($key['class']) and $key['class']!='') $class = ' class="'.$key['class'].'" ';
					else $class = '';
					if(isset($key['sort']) and $key['sort']!='') {
						if ($key['sort']!='1') $href_link = ' href="'.$key['sort'].'" ';
						else $href_link = ' href="#" ';
						
						$a1 = '<a'.$href_link.'>';
						$a2 = '</a>';
					}
					else {
						$a1 = '';
						$a2 = '';
					}
					
					$stolbbodybuf .= '<td'.$onclick.$class.'>'.$a1.$result[$key['stolb']].$a2.'</td>';
					
					foreach($sortirovka as $keydata) {
						if ($keydata['stolb']==$key['stolb']) $stolbbodybuf = str_replace('result['.$keydata['stolb'].']', $result[$key['stolb']], $stolbbodybuf);
					}
					
					
					$stolbbodybuf = str_replace('[[id]]', $result['id'], $stolbbodybuf);
					
				}
				
				if (isset($result['fl_block_id']) && $result['fl_block_id']==2) $fl_block_style = ' style="background-color: #f1cbd1;" '; else $fl_block_style = '';
				
				$stolbbody .= '<tr'.$fl_block_style.'><td>'.$increment.'</td>'.$stolbbodybuf.'</tr>';
				
				
				$increment++;
			}
			$stolbbody = str_replace('user_info[id]', $this->user_info['id'], $stolbbody);
			$stolbbody = str_replace('user_info[login]', $this->user_info['login'], $stolbbody);
		}
		
		$pagination = ' <ul class="pagination">
					  <li><a href="'.$this->admin_path.'?do='.$massfiles['adr'].'&page=1'.$search.'">&laquo;</a></li>
					  '.$pagination.'
					  <li><a href="'.$this->admin_path.'?do='.$massfiles['adr'].'&page='.$colP.$search.'">&raquo;</a></li>
					  </ul>';
		if ($colP==1) $pagination = ''; 
		
		return '
				  <!-- Table -->

				  '.$pagination.'
				  <div class="panel panel-default">
			  		<div class="panel-heading"></div>
					  <table class="table">
					    <tr><th>#</th>'.$stolbhead.'</tr>
					    '.$stolbbody.'
					  </table>
				  </div>
				  	'.$pagination.'

				  ';
	}
	

	//системыный вывод уведомлений
	function message_success()
	{
		if ($this->message_success!='') {
			return '
			<div class="alert alert-success">
				<button data-dismiss="alert" class="close" type="button">×</button>
				<h4>Поздравляем!</h4>
				<p>'.$this->message_success.'</p>
			</div>
			';
		} else return;
	}
	
	//системыный вывод уведомлений
	function message_info()
	{
		if ($this->message_info!='') {
			return '
			<div class="alert alert-info">
				<button data-dismiss="alert" class="close" type="button">×</button>
				<h4>Обратите внимание!</h4>
				<p>'.$this->message_info.'</p>
			</div>
			';
		} else return;
	}
	
	//системыный вывод уведомлений
	function message_alert()
	{
		if ($this->message_alert!='') {
			return '
			<div class="alert alert-block">
				<button data-dismiss="alert" class="close" type="button">×</button>
				<h4>Внимание!</h4>
				<p>'.$this->message_alert.'</p>
			</div>
			';
		} else return;
	}
	
	//системыный вывод уведомлений
	function message_error()
	{
		if ($this->message_error!='') {
			return '
			<div class="alert alert-error">
				<button data-dismiss="alert" class="close" type="button">×</button>
				<h4>Ошибка!</h4>
				<p>'.$this->message_error.'</p>
			</div>
			';
		} else return;
	}
		
}
?>
