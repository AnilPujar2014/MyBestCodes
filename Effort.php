<?php

class EffortsUtilsComponent extends Component
{
	//Functions for Hierarchical Compliance Tab

	public function getHierarchicalCompliance($i_data,&$io_controller)
	{
		$dates = $this->getDateRange($i_data['start_date'],$i_data['end_date']);
		$dataVal = array();
		$rotaArray = array();
		$test_array = array();

		$userefforts= $this->getEffortForAll($i_data['start_date'],$i_data['end_date'],$io_controller);

		$users = array();
		//Calling function to create nested User
		$users = $this->createNestedUser(array('start_date'=>$i_data['start_date'],'end_date'=>$i_data['end_date'],'user_id'=>0),$userefforts,$dates,$io_controller);

		return array('Date'=>$dates,'User'=>$users,'UserEffort'=>$userefforts,'test_array'=>array($test_array) );
	}

	public function createNestedUser($i_data,$UserEfforts,$Dates,&$io_controller)
	{
		$childern = array();		
		$rotaArray = array();
		$io_controller->loadModel('VActiveUser');
		$user = $io_controller->VActiveUser->find('all',array(
		'conditions'=>array(
				'supervisor_id'=>$i_data['user_id'],
				'status_id'=>1,
				'email !=' =>"NA",
				)
			));

		foreach ($user as $index => $row) {
			$subordinate_users = $this->createNestedUser(array('start_date'=>$i_data['start_date'],'end_date'=>$i_data['end_date'],'user_id'=>$row['VActiveUser']['user_id']),$UserEfforts,$Dates,$io_controller);
			if(count($subordinate_users)>0)
			{
				$TeamEffortPercentage = array();
				foreach ($Dates as $dIndex => $date) {
					$TeamEffortPercentage[$date] = sprintf('%0.2f',0);
				}
				$total_subordinate_user = count($subordinate_users);
				//$count = 0;
				foreach ($subordinate_users as $suIndex => $SubordinateUser) {

					//array_push($childern, array('name'=>$SubordinateUser['VActiveUser']['full_name'],'childern'=>$user[0]['childern']));
					//$user[0]['childern'] = array('name'=>$row['VActiveUser']['full_name'],'childern'=>$childern); 

					if (array_key_exists($SubordinateUser['VActiveUser']['user_id'], $UserEfforts)) 
					{
						$subordinate_users[$suIndex]['VActiveUser']['Effort'] = $UserEfforts[$SubordinateUser['VActiveUser']['user_id']];
						//$count+=1;
						
						$subordinate_users[$suIndex]['VActiveUser']['Rota'] = $this->getRota($SubordinateUser['VActiveUser']['user_id'],$i_data['start_date'],$i_data['end_date'],$io_controller);
						foreach ($Dates as $dateIndex => $Date) 
						{
							if(isset($subordinate_users[$suIndex]['VActiveUser']['Rota'][$Date]) && in_array($subordinate_users[$suIndex]['VActiveUser']['Rota'][$Date],array("P","L","C","Z","T"),true))
							{
								$subordinate_users[$suIndex]['VActiveUser']['ActualEfforts'][$Date] = $subordinate_users[$suIndex]['VActiveUser']['Rota'][$Date];
								$subordinate_users[$suIndex]['VActiveUser']['EffortPercentage'][$Date] = 100;
								$TeamEffortPercentage[$Date] = $TeamEffortPercentage[$Date]+100; 			
							}
							else
							{
								$subordinate_users[$suIndex]['VActiveUser']['ActualEfforts'][$Date] = gmdate("H:i:s", (int)$subordinate_users[$suIndex]['VActiveUser']['Effort'][$Date]);
								//$subordinate_users[$suIndex]['VActiveUser']['EffortPercentage'][$Date] = 100;
								//$TeamEffortPercentage[$Date] = $TeamEffortPercentage[$Date]+100; 			
								$subordinate_users[$suIndex]['VActiveUser']['EffortPercentage'][$Date] = sprintf('%0.2f',(intval($subordinate_users[$suIndex]['VActiveUser']['Effort'][$Date])/306));
								$TeamEffortPercentage[$Date] = sprintf('%0.2f',($TeamEffortPercentage[$Date]+$subordinate_users[$suIndex]['VActiveUser']['EffortPercentage'][$Date]));
							}
						}
					}
					else
					{
						$subordinate_users[$suIndex]['VActiveUser']['Rota'] = $this->getRota($SubordinateUser['VActiveUser']['user_id'],$i_data['start_date'],$i_data['end_date'],$io_controller);
						foreach ($Dates as $dateIndex => $Date) 
						{
							if(isset($subordinate_users[$suIndex]['VActiveUser']['Rota'][$Date]) && in_array($subordinate_users[$suIndex]['VActiveUser']['Rota'][$Date],array("P","L","C","Z","T"),true))
							{
								$subordinate_users[$suIndex]['VActiveUser']['ActualEfforts'][$Date] = $subordinate_users[$suIndex]['VActiveUser']['Rota'][$Date];
								$subordinate_users[$suIndex]['VActiveUser']['EffortPercentage'][$Date] = 100;
								$TeamEffortPercentage[$Date] = $TeamEffortPercentage[$Date]+100; 			
							}
							else
							{
								$subordinate_users[$suIndex]['VActiveUser']['ActualEfforts'][$Date] = "00:00:00";
								$subordinate_users[$suIndex]['VActiveUser']['EffortPercentage'][$Date] = 0;
								$TeamEffortPercentage[$Date] = $TeamEffortPercentage[$Date]+0;
							}
						}

					}
				}
				foreach ($Dates as $dIndex => $date) {
					$TeamEffortPercentage[$date] = sprintf('%0.2f',($TeamEffortPercentage[$date])/$total_subordinate_user);
				}

				
				//$user[$index]['VActiveUser']['SubordinateUsers'] = array();
				//$user[$index]['VActiveUser']['SubordinateUsers'] = $subordinate_users;
				$user[$index]['VActiveUser']['TeamEffortPercentage'] = $TeamEffortPercentage;
				//array_push($user[$index]['SubordinateUsers'],$subordinate_users);

				$user = array_merge($user,$subordinate_users);
			}
			else{
				//array_push($childern, array('name'=>$row['VActiveUser']['full_name']));
				//$user[0]['childern'] = array('name'=>$row['VActiveUser']['full_name'],'childern'=>$childern); 
			}
		}
		return $user;
	}

	public function getUserTtrsBuckets($user_id,&$io_controller)
	{
		$io_controller->loadModel('VTtrsBucketUser');
		$buckets = $io_controller->VTtrsBucketUser->find('all',array(
			'conditions'=>array(
				'user_id'=>$user_id
				),
			'fields'=>array('bucket_id','bucket_name')
			)
		);
		return $buckets;
	}

	public function getReportees($i_data,&$io_controller)
	{
		$user = $io_controller->User->find('all',array(
		'conditions'=>array(
				'supervisor_id'=>$i_data['user_id'],
				'status'=>1,
				)
			));

		foreach ($user as $index => $row) {
			$subordinate_users = $this->getReportees(array('user_id'=>$row['User']['id']),$io_controller);
			if(count($subordinate_users)>0)
			{
				$user = array_merge($user,$subordinate_users);
			}
		}

		return $user;
	}

	public function getReporteeEffortsForIncident($i_data,&$io_controller)
	{
		$io_controller->loadModel('VIncidentEffort');
		$efforts = $io_controller->VIncidentEffort->find('all',array(
		'conditions'=>array(
				'user_id'=>$i_data['reportee_id'],
				'start_time >='=>$i_data['date']." 00:00:00",
				'end_time <='=>$i_data['date']." 23:59:59"
				)
			));
		return $efforts;

	}
	public function getReporteeEffortsForTask($i_data,&$io_controller)
	{
		$io_controller->loadModel('VTaskEffort');
		$efforts = $io_controller->VTaskEffort->find('all',array(
		'conditions'=>array(
				'user_id'=>$i_data['reportee_id'],
				'start_time >='=>$i_data['date']." 00:00:00",
				'end_time <='=>$i_data['date']." 23:59:59"
				)
			));
		return $efforts;
	}
	public function getReporteeEffortsForTtrs($i_data,&$io_controller)
	{
		$io_controller->loadModel('VTtrsEffort');
		$efforts = $io_controller->VTtrsEffort->find('all',array(
		'conditions'=>array(
				'user_id'=>$i_data['reportee_id'],
				'start_time >='=>$i_data['date']." 00:00:00",
				'end_time <='=>$i_data['date']." 23:59:59"
				)
			));
		return $efforts;
	}
	public function getReporteeEffortsForProblem($i_data,&$io_controller)
	{
		$io_controller->loadModel('VProblemEffort');
		$efforts = $io_controller->VProblemEffort->find('all',array(
		'conditions'=>array(
				'user_id'=>$i_data['reportee_id'],
				'start_time >='=>$i_data['date']." 00:00:00",
				'end_time <='=>$i_data['date']." 23:59:59"
				)
			));
		return $efforts;
	}
	public function getReporteeEffortsForChange($i_data,&$io_controller)
	{
		$io_controller->loadModel('VChangeEffort');
		$efforts = $io_controller->VChangeEffort->find('all',array(
		'conditions'=>array(
				'user_id'=>$i_data['reportee_id'],
				'start_time >='=>$i_data['date']." 00:00:00",
				'end_time <='=>$i_data['date']." 23:59:59"
				)
			));
		return $efforts;
	}
	public function getReporteeEffortsForRrt($i_data,&$io_controller)
	{
		$io_controller->loadModel('VRrtEffort');
		$efforts = $io_controller->VRrtEffort->find('all',array(
		'conditions'=>array(
				'user_id'=>$i_data['reportee_id'],
				'start_time >='=>$i_data['date']." 00:00:00",
				'end_time <='=>$i_data['date']." 23:59:59"
				)
			));
		return $efforts;
	}
	public function getReporteeEfforts($i_data,&$io_controller)
	{
		$incidentEfforts = $this->getIncidentEffort($i_data['reportee_id'],$i_data['selected_date']." 00:00:00",$i_data['selected_date']." 23:59:59","","",$io_controller);
		$ttrsEfforts = $this->getTtrsEffort($i_data['reportee_id'],$i_data['selected_date']." 00:00:00",$i_data['selected_date']." 23:59:59","","",$io_controller);
		$taskEfforts = $this->getTaskEffort($i_data['reportee_id'],$i_data['selected_date']." 00:00:00",$i_data['selected_date']." 23:59:59","","",$io_controller);
		$problemEfforts = $this->getProblemEffort($i_data['reportee_id'],$i_data['selected_date']." 00:00:00",$i_data['selected_date']." 23:59:59","","",$io_controller);
		$changeEfforts = $this->getChangeEffort($i_data['reportee_id'],$i_data['selected_date']." 00:00:00",$i_data['selected_date']." 23:59:59","","",$io_controller);
		$rrtEfforts = $this->getRrtEffort($i_data['reportee_id'],$i_data['selected_date']." 00:00:00",$i_data['selected_date']." 23:59:59","","",$io_controller);

		return array(
				'incident'=>$incidentEfforts,
				'ttrs'=>$ttrsEfforts,
				'task'=>$taskEfforts,
				'problem'=>$problemEfforts,
				'change'=>$changeEfforts,
				'rrt'=>$rrtEfforts
			);
	}



	public function getDateEfforts($dates,$retVal,$retType)
	{
		$effort = array();
		foreach ($dates as $dateIndex => $curdate) {
			$found = false;
			foreach ($retVal as $row) {
				if($row['0']['date'] == $curdate)
				{
					$effort[$curdate] = $row['0']['effort'];
					$found = true;
				}
			}
			if($found == false)
			{
				if($retType == "time")
				{	
					$effort[$curdate] = "00:00:00";
				}
				else
				{
					$effort[$curdate] = "0";
				}
			}			
		}
		return $effort;

	}

	public function getUserDateEfforts($dates,$retVal)
	{
		$usereffort = array();
		foreach ($retVal as $row) {
			if(!isset($usereffort[$row['e']['user_id']]))
			{
				$usereffort[$row['e']['user_id']] = array();
			}
			foreach ($dates as $dateIndex => $curdate) {
				$found = false;
				
				if($row['0']['date'] == $curdate)
				{
					$usereffort[$row['e']['user_id']][$curdate] = $row['0']['effort'];
					$found = true;
				}				
			}			
		}
		foreach ($usereffort as $userId => &$userarray) {
			foreach ($dates as $dateIndex => $curdate) {
				if(!isset($userarray[$curdate]))
				{
					$usereffort[$userId][$curdate] = 0;
				}
			}					
		}

		return $usereffort;
	}

	/* Functions below are used for compliance report */

	public function getEffortForAll($start_date,$end_date,&$io_controller)
	{
		$dates = $this->getDateRange($start_date,$end_date);
		$userefforts = array();

		$incidentEfforts = $this->getIncidentEffortForAll($start_date,$end_date,$io_controller);
		$ttrsEfforts = $this->getTtrsEffortForAll($start_date,$end_date,$io_controller);
		$taskEfforts = $this->getTaskEffortForAll($start_date,$end_date,$io_controller);
		$problemEfforts = $this->getProblemEffortForAll($start_date,$end_date,$io_controller);
		$changeEfforts = $this->getChangeEffortForAll($start_date,$end_date,$io_controller);
		$rrtEfforts = $this->getRrtEffortForAll($start_date,$end_date,$io_controller);

		foreach ($dates as $dateIndex => $curdate) {
			foreach ($incidentEfforts as $userId => $effort) {
				if(!isset($userefforts[$userId]))
				{
					$userefforts[$userId]=array();
				}
				if(!isset($userefforts[$userId][$curdate]))
				{
					$userefforts[$userId][$curdate]=0;
				}
				$userefforts[$userId][$curdate] = $userefforts[$userId][$curdate] + $effort[$curdate];
			}
			foreach ($taskEfforts as $userId => $effort) {
				if(!isset($userefforts[$userId]))
				{
					$userefforts[$userId]=array();
				}
				if(!isset($userefforts[$userId][$curdate]))
				{
					$userefforts[$userId][$curdate]=0;
				}
				$userefforts[$userId][$curdate] = $userefforts[$userId][$curdate] + $effort[$curdate];
			}
			foreach ($ttrsEfforts as $userId => $effort) {
				if(!isset($userefforts[$userId]))
				{
					$userefforts[$userId]=array();
				}
				if(!isset($userefforts[$userId][$curdate]))
				{
					$userefforts[$userId][$curdate]=0;
				}
				$userefforts[$userId][$curdate] = $userefforts[$userId][$curdate] + $effort[$curdate];
			}
			foreach ($changeEfforts as $userId => $effort) {
				if(!isset($userefforts[$userId]))
				{
					$userefforts[$userId]=array();
				}
				if(!isset($userefforts[$userId][$curdate]))
				{
					$userefforts[$userId][$curdate]=0;
				}
				$userefforts[$userId][$curdate] = $userefforts[$userId][$curdate] + $effort[$curdate];
			}
			foreach ($problemEfforts as $userId => $effort) {
				if(!isset($userefforts[$userId]))
				{
					$userefforts[$userId]=array();
				}
				if(!isset($userefforts[$userId][$curdate]))
				{
					$userefforts[$userId][$curdate]=0;
				}
				$userefforts[$userId][$curdate] = $userefforts[$userId][$curdate] + $effort[$curdate];
			}
			foreach ($rrtEfforts as $userId => $effort) {
				if(!isset($userefforts[$userId]))
				{
					$userefforts[$userId]=array();
				}
				if(!isset($userefforts[$userId][$curdate]))
				{
					$userefforts[$userId][$curdate]=0;
				}
				$userefforts[$userId][$curdate] = $userefforts[$userId][$curdate] + $effort[$curdate];
			}
		}

		return $userefforts;
	}
	public function getIncidentEffortForAll($start_date,$end_date,&$io_controller)
	{
		$dates = $this->getDateRange($start_date,$end_date);
		$io_controller->loadModel('Incident');
		$incidenteffort_query="select user_id,DATE(start_time) as date,(SUM(TIME_TO_SEC(difference))) as effort 
				from v_incident_efforts e where user_status =1 and start_time>=? and end_time<=? group by user_id,DATE(start_time)";
		$retVal =  $io_controller->Incident->query($incidenteffort_query,array($start_date." 00:00:00",$end_date." 23:59:59"));
		return $this->getUserDateEfforts($dates,$retVal);
	}
	public function getTtrsEffortForAll($start_date,$end_date,&$io_controller)
	{
		$dates = $this->getDateRange($start_date,$end_date);
		$io_controller->loadModel('Incident');
		$incidenteffort_query="select user_id,DATE(start_time) as date,(SUM(TIME_TO_SEC(difference))) as effort 
				from v_ttrs_efforts e where user_status =1 and start_time>=? and end_time<=? group by user_id,DATE(start_time)";
		$retVal =  $io_controller->Incident->query($incidenteffort_query,array($start_date." 00:00:00",$end_date." 23:59:59"));
		return $this->getUserDateEfforts($dates,$retVal);
	}
	public function getTaskEffortForAll($start_date,$end_date,&$io_controller)
	{
		$dates = $this->getDateRange($start_date,$end_date);
		$io_controller->loadModel('Incident');
		$incidenteffort_query="select user_id,DATE(start_time) as date,(SUM(TIME_TO_SEC(difference))) as effort 
				from v_task_efforts e where user_status =1 and start_time>=? and end_time<=? group by user_id,DATE(start_time)";
		$retVal =  $io_controller->Incident->query($incidenteffort_query,array($start_date." 00:00:00",$end_date." 23:59:59"));
		return $this->getUserDateEfforts($dates,$retVal);
	}
	public function getProblemEffortForAll($start_date,$end_date,&$io_controller)
	{
		$dates = $this->getDateRange($start_date,$end_date);
		$io_controller->loadModel('Incident');
		$incidenteffort_query="select user_id,DATE(start_time) as date,(SUM(TIME_TO_SEC(difference))) as effort 
				from v_problem_efforts e where user_status =1 and start_time>=? and end_time<=? group by user_id,DATE(start_time)";
		$retVal =  $io_controller->Incident->query($incidenteffort_query,array($start_date." 00:00:00",$end_date." 23:59:59"));
		return $this->getUserDateEfforts($dates,$retVal);
	}
	public function getChangeEffortForAll($start_date,$end_date,&$io_controller)
	{
		$dates = $this->getDateRange($start_date,$end_date);
		$io_controller->loadModel('Incident');
		$incidenteffort_query="select user_id,DATE(start_time) as date,(SUM(TIME_TO_SEC(difference))) as effort 
				from v_change_efforts e where user_status =1 and start_time>=? and end_time<=? group by user_id,DATE(start_time)";
		$retVal =  $io_controller->Incident->query($incidenteffort_query,array($start_date." 00:00:00",$end_date." 23:59:59"));
		return $this->getUserDateEfforts($dates,$retVal);
	}
	public function getRrtEffortForAll($start_date,$end_date,&$io_controller)
	{
		$dates = $this->getDateRange($start_date,$end_date);
		$io_controller->loadModel('Incident');
		$incidenteffort_query="select user_id,DATE(start_time) as date,(SUM(TIME_TO_SEC(difference))) as effort 
				from v_rrt_efforts e where user_status =1 and start_time>=? and end_time<=? group by user_id,DATE(start_time)";
		$retVal =  $io_controller->Incident->query($incidenteffort_query,array($start_date." 00:00:00",$end_date." 23:59:59"));
		return $this->getUserDateEfforts($dates,$retVal);
	}

	/* --Functions above are used for compliance report */

	public function getIncidentEffort($user_id,$start_date,$end_date,$bucket_id,$retType,&$io_controller)
	{
		$func = "";
		$dates = $this->getDateRange($start_date,$end_date);
		if($retType == "time")
		{
			$func = "SEC_TO_TIME";
		}

		$io_controller->loadModel('Incident');

		if($bucket_id != "")
		{
			$incidenteffort_query="select DATE(start_time) as date,".$func."(SUM(TIME_TO_SEC(difference))) as effort 
				from v_incident_efforts where user_id=? and start_time>=? and end_time<=? and bucket_id = ? group by bucket_id,user_id,DATE(start_time)";
			$retVal =  $io_controller->Incident->query($incidenteffort_query,array($user_id,$start_date." 00:00:00",$end_date." 23:59:59",$bucket_id));
		}
		else
		{
			$incidenteffort_query="select DATE(start_time) as date,".$func."(SUM(TIME_TO_SEC(difference))) as effort 
				from v_incident_efforts where user_id=? and start_time>=? and end_time<=? group by DATE(start_time)";
			$retVal =  $io_controller->Incident->query($incidenteffort_query,array($user_id,$start_date." 00:00:00",$end_date." 23:59:59"));

		}
		
		return $this->getDateEfforts($dates,$retVal,$retType);
	}

	public function getTtrsEffort($user_id,$start_date,$end_date,$bucket_id,$retType,&$io_controller)
	{

		$func = "";
		$dates = $this->getDateRange($start_date,$end_date);
		if($retType == "time")
		{
			$func = "SEC_TO_TIME";
		}

		$io_controller->loadModel('Ttrs');

		if($bucket_id != "")
		{
			$ttrseffort_query="select DATE(start_time) as date,".$func."(SUM(TIME_TO_SEC(difference))) as effort 
				from v_ttrs_efforts where user_id=? and start_time>=? and end_time<=? and bucket_id = ? group by bucket_id,user_id,DATE(start_time)";
			$retVal =  $io_controller->TTrs->query($ttrseffort_query,array($user_id,$start_date." 00:00:00",$end_date." 23:59:59",$bucket_id));

		}
		else
		{
			$ttrseffort_query="select DATE(start_time) as date,".$func."(SUM(TIME_TO_SEC(difference))) as effort 
				from v_ttrs_efforts where user_id=? and start_time>=? and end_time<=? group by DATE(start_time)";
			$retVal =  $io_controller->Ttrs->query($ttrseffort_query,array($user_id,$start_date." 00:00:00",$end_date." 23:59:59"));

		}
		
		
		return $this->getDateEfforts($dates,$retVal,$retType);		
		
	}

	public function getTaskEffort($user_id,$start_date,$end_date,$bucket_id,$retType,&$io_controller)
	{

		$func = "";
		$dates = $this->getDateRange($start_date,$end_date);
		if($retType == "time")
		{
			$func = "SEC_TO_TIME";
		}

		$io_controller->loadModel('Task');

		if($bucket_id != "")
		{
			$ttrseffort_query="select DATE(start_time) as date,".$func."(SUM(TIME_TO_SEC(difference))) as effort 
				from v_task_efforts where user_id=? and start_time>=? and end_time<=? and bucket_id = ? group by bucket_id,user_id,DATE(start_time)";
			$retVal =  $io_controller->Task->query($ttrseffort_query,array($user_id,$start_date." 00:00:00",$end_date." 23:59:59",$bucket_id));

		}
		else
		{
			$ttrseffort_query="select DATE(start_time) as date,".$func."(SUM(TIME_TO_SEC(difference))) as effort 
				from v_task_efforts where user_id=? and start_time>=? and end_time<=? group by DATE(start_time)";
			$retVal =  $io_controller->Task->query($ttrseffort_query,array($user_id,$start_date." 00:00:00",$end_date." 23:59:59"));

		}
		
		
		return $this->getDateEfforts($dates,$retVal,$retType);	
		
	}

	public function getProblemEffort($user_id,$start_date,$end_date,$bucket_id,$retType,&$io_controller)
	{

		$func = "";
		$dates = $this->getDateRange($start_date,$end_date);
		if($retType == "time")
		{
			$func = "SEC_TO_TIME";
		}

		$io_controller->loadModel('Task');

		if($bucket_id != "")
		{
			$ttrseffort_query="select DATE(start_time) as date,".$func."(SUM(TIME_TO_SEC(difference))) as effort 
				from v_problem_efforts where user_id=? and start_time>=? and end_time<=? and bucket_id = ? group by bucket_id,user_id,DATE(start_time)";
			$retVal =  $io_controller->Task->query($ttrseffort_query,array($user_id,$start_date." 00:00:00",$end_date." 23:59:59",$bucket_id));

		}
		else
		{
			$ttrseffort_query="select DATE(start_time) as date,".$func."(SUM(TIME_TO_SEC(difference))) as effort 
				from v_problem_efforts where user_id=? and start_time>=? and end_time<=? group by DATE(start_time)";
			$retVal =  $io_controller->Task->query($ttrseffort_query,array($user_id,$start_date." 00:00:00",$end_date." 23:59:59"));

		}
		
		
		return $this->getDateEfforts($dates,$retVal,$retType);		
		
	}

	public function getChangeEffort($user_id,$start_date,$end_date,$bucket_id,$retType,&$io_controller)
	{

		$func = "";
		$dates = $this->getDateRange($start_date,$end_date);
		if($retType == "time")
		{
			$func = "SEC_TO_TIME";
		}

		$io_controller->loadModel('Task');

		if($bucket_id != "")
		{
			$ttrseffort_query="select DATE(start_time) as date,".$func."(SUM(TIME_TO_SEC(difference))) as effort 
				from v_change_efforts where user_id=? and start_time>=? and end_time<=? and bucket_id = ? group by bucket_id,user_id,DATE(start_time)";
			$retVal =  $io_controller->Task->query($ttrseffort_query,array($user_id,$start_date." 00:00:00",$end_date." 23:59:59",$bucket_id));

		}
		else
		{
			$ttrseffort_query="select DATE(start_time) as date,".$func."(SUM(TIME_TO_SEC(difference))) as effort 
				from v_change_efforts where user_id=? and start_time>=? and end_time<=? group by DATE(start_time)";
			$retVal =  $io_controller->Task->query($ttrseffort_query,array($user_id,$start_date." 00:00:00",$end_date." 23:59:59"));

		}
		
		return $this->getDateEfforts($dates,$retVal,$retType);		
		
	}

	public function getRrtEffort($user_id,$start_date,$end_date,$project_id,$retType,&$io_controller)
	{

		$func = "";
		$dates = $this->getDateRange($start_date,$end_date);
		if($retType == "time")
		{
			$func = "SEC_TO_TIME";
		}

		$io_controller->loadModel('Task');

		if($project_id != "")
		{
			$ttrseffort_query="select DATE(start_time) as date,".$func."(SUM(TIME_TO_SEC(difference))) as effort 
				from v_rrt_efforts where user_id=? and start_time>=? and end_time<=? and rrt_id = ? group by bucket_id,user_id,DATE(start_time)";
			$retVal =  $io_controller->Task->query($ttrseffort_query,array($user_id,$start_date." 00:00:00",$end_date." 23:59:59",$project_id));

		}
		else
		{
			$ttrseffort_query="select DATE(start_time) as date,".$func."(SUM(TIME_TO_SEC(difference))) as effort 
				from v_rrt_efforts where user_id=? and start_time>=? and end_time<=? group by DATE(start_time)";
			$retVal =  $io_controller->Task->query($ttrseffort_query,array($user_id,$start_date." 00:00:00",$end_date." 23:59:59"));

		}
		
		
		return $this->getDateEfforts($dates,$retVal,$retType);	
		
	}



	public function downloadTtrsReport($i_data,&$io_controller)
	{
		//Get all the users
		//
		$userList=$io_controller->User->query("select * from (select Users.id, concat(Users.first_name,' ', Users.last_name) as full_name  from users as Users ORDER BY full_name) as User ");

		//Get the date range and store it in a array
		//
		$dates = array();
		$start_date = new DateTime($i_data['start_date']);
		$end_date = new DateTime($i_data['end_date']);
		$curdate = $start_date;
		while(($end_date->getTimestamp() - $curdate->getTimestamp()) > 0) { 
			$str = $curdate->format('Y-m-d');
			array_push($dates,$str);
			$curdate->add(new DateInterval("P1D"));
		}

		$ttrsBucketLeads = array();
		$ttrsBucketLeads = array();

		$fh = fopen("ttrs_effort_report.csv","w+");

		$header = "Name,Stream,Bucket,ServiceManager,ServiceLead,";
		foreach ($dates as $dateIndex => $curdate) {
			$header= $header.$curdate.",";
		}
		fwrite($fh, $header."\n");
		$ttrs_write_data = "";
		//For each user and date
		//
		foreach ($userList as $userIndex => $user) {
			
			$ttrsBuckets = $this->getUserTtrsBuckets($user['User']['id'],$io_controller);

			foreach ($ttrsBuckets as $bucketIndex => $bucket) {
				
				$ttrs_write_data = $user['User']['full_name'].","."Ttrs".",".$bucket['VTtrsBucketUser']['bucket_name'].",";
				//Get the service lead and manager
				if(!isset($ttrsBucketLeads[$bucket['VTtrsBucketUser']['bucket_id']]))
				{
					$ttrsBucketLeads[$bucket['VTtrsBucketUser']['bucket_id']] = $this->getLeadsForTtrsBucket($bucket['VTtrsBucketUser']['bucket_id'],$io_controller);
				}

				foreach ($ttrsBucketLeads[$bucket['VTtrsBucketUser']['bucket_id']]['sm'] as $key => $value) {
					$ttrs_write_data = $ttrs_write_data.$value['VTtrsBucketUser']['full_name'].";";
				}
				$ttrs_write_data = $ttrs_write_data.",";
				foreach ($ttrsBucketLeads[$bucket['VTtrsBucketUser']['bucket_id']]['sl'] as $key => $value) {
					$ttrs_write_data = $ttrs_write_data.$value['VTtrsBucketUser']['full_name'].";";
				}
				$ttrs_write_data = $ttrs_write_data.",";					
				foreach ($dates as $dateIndex => $curdate) {
				
					//Get the Ttrs effort
					//
					$ttrsEffort = $this->getTtrsEffort($user['User']['id'],$curdate,$bucket['VTtrsBucketUser']['bucket_id'],$io_controller);
					$ttrs_write_data = $ttrs_write_data.$ttrsEffort.",";					
				}
				fwrite($fh, $ttrs_write_data."\n");


			}
		}
		fclose($fh);
	}

	public function getUserIncidentBuckets($user_id,&$io_controller)
	{
		$io_controller->loadModel('VIncidentBucketUser');
		$buckets = $io_controller->VIncidentBucketUser->find('all',array(
			'conditions'=>array(
				'user_id'=>$user_id
				),
			'fields'=>array('bucket_id','bucket_name')
			)
		);
		return $buckets;
	}

	public function downloadIncidentReport($i_data,&$io_controller)
	{
		//Get all the users
		//
		$userList=$io_controller->User->query("select * from (select Users.id, concat(Users.first_name,' ', Users.last_name) as full_name  from users as Users ORDER BY full_name) as User ");

		//Get the date range and store it in a array
		//
		$dates = array();
		$start_date = new DateTime($i_data['start_date']);
		$end_date = new DateTime($i_data['end_date']);
		$curdate = $start_date;
		while(($end_date->getTimestamp() - $curdate->getTimestamp()) > 0) { 
			$str = $curdate->format('Y-m-d');
			array_push($dates,$str);
			$curdate->add(new DateInterval("P1D"));
		}

		$incidentBucketLeads = array();
		$ttrsBucketLeads = array();

		$fh = fopen("incident_effort_report.csv","w+");

		$header = "Name,Stream,Bucket,ServiceManager,ServiceLead,";
		foreach ($dates as $dateIndex => $curdate) {
			$header= $header.$curdate.",";
		}
		fwrite($fh, $header."\n");
		$incident_write_data = "";
		//For each user and date
		//
		foreach ($userList as $userIndex => $user) {
			
			$incidentBuckets = $this->getUserIncidentBuckets($user['User']['id'],$io_controller);

			foreach ($incidentBuckets as $bucketIndex => $bucket) {
				
				$incident_write_data = $user['User']['full_name'].","."Incident".",".$bucket['VIncidentBucketUser']['bucket_name'].",";
				//Get the service lead and manager
				if(!isset($incidentBucketLeads[$bucket['VIncidentBucketUser']['bucket_id']]))
				{
					$incidentBucketLeads[$bucket['VIncidentBucketUser']['bucket_id']] = $this->getLeadsForIncidentBucket($bucket['VIncidentBucketUser']['bucket_id'],$io_controller);
				}

				foreach ($incidentBucketLeads[$bucket['VIncidentBucketUser']['bucket_id']]['sm'] as $key => $value) {
					$incident_write_data = $incident_write_data.$value['VIncidentBucketUser']['full_name'].";";
				}
				$incident_write_data = $incident_write_data.",";
				foreach ($incidentBucketLeads[$bucket['VIncidentBucketUser']['bucket_id']]['sl'] as $key => $value) {
					$incident_write_data = $incident_write_data.$value['VIncidentBucketUser']['full_name'].";";
				}
				$incident_write_data = $incident_write_data.",";					
				foreach ($dates as $dateIndex => $curdate) {
				
					//Get the Incident effort
					//
					$incidentEffort = $this->getIncidentEffort($user['User']['id'],$curdate,$curdate,$bucket['VIncidentBucketUser']['bucket_id'],"time",$io_controller);
					$incident_write_data = $incident_write_data.$incidentEffort[$curdate].",";					
				}
				fwrite($fh, $incident_write_data."\n");


			}
		}
		fclose($fh);
	}

	public function getUserTaskBuckets($user_id,&$io_controller)
	{
		$io_controller->loadModel('VTaskBucketUser');
		$buckets = $io_controller->VTaskBucketUser->find('all',array(
			'conditions'=>array(
				'user_id'=>$user_id
				),
			'fields'=>array('bucket_id','bucket_name')
			)
		);
		return $buckets;
	}

	/* Functions to retrieve leads for every streams */

	public function getLeadsForIncidentBucket($bucket_id,&$io_controller)
	{
		$io_controller->loadModel('VIncidentBucketUser');
		$serviceManagers = $io_controller->VIncidentBucketUser->find('all',array(
			'conditions'=>array(
				'role_name'=>'Service Manager',
				'bucket_id'=>$bucket_id
				),
			'fields'=>array('full_name','email')
			)
		);
		$serviceLeads = $io_controller->VIncidentBucketUser->find('all',array(
			'conditions'=>array(
				'role_name LIKE'=>'%Service Lead%',
				'bucket_id'=>$bucket_id
				),
			'fields'=>array('full_name','email')
			)
		);

		return array('sm'=>$serviceManagers,'sl'=>$serviceLeads);
	}

	public function getLeadsForTaskBucket($bucket_id,&$io_controller)
	{
		$io_controller->loadModel('VTaskBucketUser');
		$serviceManagers = $io_controller->VTaskBucketUser->find('all',array(
			'conditions'=>array(
				'role_name'=>'Service Manager',
				'bucket_id'=>$bucket_id
				),
			'fields'=>array('full_name')
			)
		);
		$serviceLeads = $io_controller->VTaskBucketUser->find('all',array(
			'conditions'=>array(
				'role_name LIKE'=>'%Service Lead%',
				'bucket_id'=>$bucket_id
				),
			'fields'=>array('full_name')
			)
		);

		return array('sm'=>$serviceManagers,'sl'=>$serviceLeads);
	}

	public function getLeadsForTtrsBucket($bucket_id,&$io_controller)
	{
		$io_controller->loadModel('VTtrsBucketUser');
		$serviceManagers = $io_controller->VTtrsBucketUser->find('all',array(
			'conditions'=>array(
				'role_name'=>'Service Manager',
				'bucket_id'=>$bucket_id
				),
			'fields'=>array('full_name')
			)
		);
		$serviceLeads = $io_controller->VTtrsBucketUser->find('all',array(
			'conditions'=>array(
				'role_name LIKE'=>'%Service Lead%',
				'bucket_id'=>$bucket_id
				),
			'fields'=>array('full_name')
			)
		);

		return array('sm'=>$serviceManagers,'sl'=>$serviceLeads);
	}

	public function getLeadsForProblemBucket($bucket_id,&$io_controller)
	{
		$io_controller->loadModel('VProblemBucketUser');
		$serviceManagers = $io_controller->VProblemBucketUser->find('all',array(
			'conditions'=>array(
				'role_name'=>'Service Manager',
				'bucket_id'=>$bucket_id
				),
			'fields'=>array('full_name')
			)
		);
		$serviceLeads = $io_controller->VProblemBucketUser->find('all',array(
			'conditions'=>array(
				'role_name LIKE'=>'%Service Lead%',
				'bucket_id'=>$bucket_id
				),
			'fields'=>array('full_name')
			)
		);

		$io_controller->logMsg('info',print_r($serviceManagers,true));
		$io_controller->logMsg('info',print_r($serviceLeads,true));
		return array('sm'=>$serviceManagers,'sl'=>$serviceLeads);
	}

	public function getLeadsForChangeBucket($bucket_id,&$io_controller)
	{
		$io_controller->loadModel('VChangeBucketUser');
		$serviceManagers = $io_controller->VChangeBucketUser->find('all',array(
			'conditions'=>array(
				'role_name'=>'Service Manager',
				'bucket_id'=>$bucket_id
				),
			'fields'=>array('full_name')
			)
		);
		$serviceLeads = $io_controller->VChangeBucketUser->find('all',array(
			'conditions'=>array(
				'role_name LIKE'=>'%Service Lead%',
				'bucket_id'=>$bucket_id
				),
			'fields'=>array('full_name')
			)
		);

		$io_controller->logMsg('info',print_r($serviceManagers,true));
		$io_controller->logMsg('info',print_r($serviceLeads,true));
		return array('sm'=>$serviceManagers,'sl'=>$serviceLeads);
	}

	/* --Functions to retrieve leads for every streams */

	public function downloadTaskReport($i_data,&$io_controller)
	{
		//Get all the users
		//
		$userList=$io_controller->User->query("select * from (select Users.id, concat(Users.first_name,' ', Users.last_name) as full_name  from users as Users ORDER BY full_name) as User ");

		//Get the date range and store it in a array
		//
		$dates = array();
		$start_date = new DateTime($i_data['start_date']);
		$end_date = new DateTime($i_data['end_date']);
		$curdate = $start_date;
		while(($end_date->getTimestamp() - $curdate->getTimestamp()) > 0) { 
			$str = $curdate->format('Y-m-d');
			array_push($dates,$str);
			$curdate->add(new DateInterval("P1D"));
		}

		$taskBucketLeads = array();
		$ttrsBucketLeads = array();

		$fh = fopen("task_effort_report.csv","w+");

		$header = "Name,Stream,Bucket,ServiceManager,ServiceLead,";
		foreach ($dates as $dateIndex => $curdate) {
			$header= $header.$curdate.",";
		}
		fwrite($fh, $header."\n");
		$task_write_data = "";
		//For each user and date
		//
		foreach ($userList as $userIndex => $user) {
			
			$taskBuckets = $this->getUserTaskBuckets($user['User']['id'],$io_controller);

			foreach ($taskBuckets as $bucketIndex => $bucket) {
				
				$task_write_data = $user['User']['full_name'].","."Task".",".$bucket['VTaskBucketUser']['bucket_name'].",";
				//Get the service lead and manager
				if(!isset($taskBucketLeads[$bucket['VTaskBucketUser']['bucket_id']]))
				{
					$taskBucketLeads[$bucket['VTaskBucketUser']['bucket_id']] = $this->getLeadsForTaskBucket($bucket['VTaskBucketUser']['bucket_id'],$io_controller);
				}

				foreach ($taskBucketLeads[$bucket['VTaskBucketUser']['bucket_id']]['sm'] as $key => $value) {
					$task_write_data = $task_write_data.$value['VTaskBucketUser']['full_name'].";";
				}
				$task_write_data = $task_write_data.",";
				foreach ($taskBucketLeads[$bucket['VTaskBucketUser']['bucket_id']]['sl'] as $key => $value) {
					$task_write_data = $task_write_data.$value['VTaskBucketUser']['full_name'].";";
				}
				$task_write_data = $task_write_data.",";					
				foreach ($dates as $dateIndex => $curdate) {
				
					//Get the Task effort
					//
					$taskEffort = $this->getTaskEffort($user['User']['id'],$curdate,$curdate,$bucket['VTaskBucketUser']['bucket_id'],"time",$io_controller);
					$task_write_data = $task_write_data.$taskEffort[$curdate].",";					
				}
				fwrite($fh, $task_write_data."\n");


			}
		}
		fclose($fh);
	}

	public function getUserProblemBuckets($user_id,&$io_controller)
	{
		$io_controller->loadModel('VProblemBucketUser');
		$buckets = $io_controller->VProblemBucketUser->find('all',array(
			'conditions'=>array(
				'user_id'=>$user_id
				),
			'fields'=>array('bucket_id','bucket_name')
			)
		);
		return $buckets;
	}



	public function downloadProblemReport($i_data,&$io_controller)
	{
		//Get all the users
		//
		$userList=$io_controller->User->query("select * from (select Users.id, concat(Users.first_name,' ', Users.last_name) as full_name  from users as Users ORDER BY full_name) as User ");

		//Get the date range and store it in a array
		//
		$dates = array();
		$start_date = new DateTime($i_data['start_date']);
		$end_date = new DateTime($i_data['end_date']);
		$curdate = $start_date;
		while(($end_date->getTimestamp() - $curdate->getTimestamp()) > 0) { 
			$str = $curdate->format('Y-m-d');
			array_push($dates,$str);
			$curdate->add(new DateInterval("P1D"));
		}

		$problemBucketLeads = array();
		$ttrsBucketLeads = array();

		$fh = fopen("problem_effort_report.csv","w+");

		$header = "Name,Stream,Bucket,ServiceManager,ServiceLead,";
		foreach ($dates as $dateIndex => $curdate) {
			$header= $header.$curdate.",";
		}
		fwrite($fh, $header."\n");
		$problem_write_data = "";
		//For each user and date
		//
		foreach ($userList as $userIndex => $user) {
			
			$problemBuckets = $this->getUserProblemBuckets($user['User']['id'],$io_controller);

			foreach ($problemBuckets as $bucketIndex => $bucket) {
				
				$problem_write_data = $user['User']['full_name'].","."Problem".",".$bucket['VProblemBucketUser']['bucket_name'].",";
				//Get the service lead and manager
				if(!isset($problemBucketLeads[$bucket['VProblemBucketUser']['bucket_id']]))
				{
					$problemBucketLeads[$bucket['VProblemBucketUser']['bucket_id']] = $this->getLeadsForProblemBucket($bucket['VProblemBucketUser']['bucket_id'],$io_controller);
				}

				foreach ($problemBucketLeads[$bucket['VProblemBucketUser']['bucket_id']]['sm'] as $key => $value) {
					$problem_write_data = $problem_write_data.$value['VProblemBucketUser']['full_name'].";";
				}
				$problem_write_data = $problem_write_data.",";
				foreach ($problemBucketLeads[$bucket['VProblemBucketUser']['bucket_id']]['sl'] as $key => $value) {
					$problem_write_data = $problem_write_data.$value['VProblemBucketUser']['full_name'].";";
				}
				$problem_write_data = $problem_write_data.",";					
				foreach ($dates as $dateIndex => $curdate) {
				
					//Get the Problem effort
					//
					$problemEffort = $this->getProblemEffort($user['User']['id'],$curdate,$bucket['VProblemBucketUser']['bucket_id'],$io_controller);
					$problem_write_data = $problem_write_data.$problemEffort.",";					
				}
				fwrite($fh, $problem_write_data."\n");


			}
		}
		fclose($fh);
	}

	public function getUserChangeBuckets($user_id,&$io_controller)
	{
		$io_controller->loadModel('VChangeBucketUser');
		$buckets = $io_controller->VChangeBucketUser->find('all',array(
			'conditions'=>array(
				'user_id'=>$user_id
				),
			'fields'=>array('bucket_id','bucket_name')
			)
		);
		return $buckets;
	}


	public function downloadChangeReport($i_data,&$io_controller)
	{
		//Get all the users
		//
		$userList=$io_controller->User->query("select * from (select Users.id, concat(Users.first_name,' ', Users.last_name) as full_name  from users as Users ORDER BY full_name) as User ");

		//Get the date range and store it in a array
		//
		$dates = array();
		$start_date = new DateTime($i_data['start_date']);
		$end_date = new DateTime($i_data['end_date']);
		$curdate = $start_date;
		while(($end_date->getTimestamp() - $curdate->getTimestamp()) > 0) { 
			$str = $curdate->format('Y-m-d');
			array_push($dates,$str);
			$curdate->add(new DateInterval("P1D"));
		}

		$changeBucketLeads = array();
		$ttrsBucketLeads = array();

		$fh = fopen("change_effort_report.csv","w+");

		$header = "Name,Stream,Bucket,ServiceManager,ServiceLead,";
		foreach ($dates as $dateIndex => $curdate) {
			$header= $header.$curdate.",";
		}
		fwrite($fh, $header."\n");
		$change_write_data = "";
		//For each user and date
		//
		foreach ($userList as $userIndex => $user) {
			
			$changeBuckets = $this->getUserChangeBuckets($user['User']['id'],$io_controller);

			foreach ($changeBuckets as $bucketIndex => $bucket) {
				
				$change_write_data = $user['User']['full_name'].","."Change".",".$bucket['VChangeBucketUser']['bucket_name'].",";
				//Get the service lead and manager
				if(!isset($changeBucketLeads[$bucket['VChangeBucketUser']['bucket_id']]))
				{
					$changeBucketLeads[$bucket['VChangeBucketUser']['bucket_id']] = $this->getLeadsForChangeBucket($bucket['VChangeBucketUser']['bucket_id'],$io_controller);
				}

				foreach ($changeBucketLeads[$bucket['VChangeBucketUser']['bucket_id']]['sm'] as $key => $value) {
					$change_write_data = $change_write_data.$value['VChangeBucketUser']['full_name'].";";
				}
				$change_write_data = $change_write_data.",";
				foreach ($changeBucketLeads[$bucket['VChangeBucketUser']['bucket_id']]['sl'] as $key => $value) {
					$change_write_data = $change_write_data.$value['VChangeBucketUser']['full_name'].";";
				}
				$change_write_data = $change_write_data.",";					
				foreach ($dates as $dateIndex => $curdate) {
				
					//Get the Change effort
					//
					$changeEffort = $this->getChangeEffort($user['User']['id'],$curdate,$bucket['VChangeBucketUser']['bucket_id'],$io_controller);
					$change_write_data = $change_write_data.$changeEffort.",";					
				}
				fwrite($fh, $change_write_data."\n");


			}
		}
		fclose($fh);
	}

	public function getUserRrtProjects($user_id,&$io_controller)
	{
		$rrtEffort_query="select rrt_id,project_name from v_rrt_efforts where user_id=? group by rrt_id";
		$io_controller->loadModel('RrtProject');
		$retVal =  $io_controller->RrtProject->query($rrtEffort_query,array($user_id));
		
		return $retVal;
	}

	public function downloadRrtReport($i_data,&$io_controller)
	{
		//Get all the users
		//
		$userList=$io_controller->User->query("select * from (select Users.id, concat(Users.first_name,' ', Users.last_name) as full_name,status  from users as Users ORDER BY full_name) as User where User.status = 1");

		//Get the date range and store it in a array
		//
		$dates = array();
		$start_date = new DateTime($i_data['start_date']);
		$end_date = new DateTime($i_data['end_date']);
		$curdate = $start_date;
		while(($end_date->getTimestamp() - $curdate->getTimestamp()) > 0) { 
			$str = $curdate->format('Y-m-d');
			array_push($dates,$str);
			$curdate->add(new DateInterval("P1D"));
		}

		$fh = fopen("rrt_effort_report.csv","w+");

		$header = "Name,Stream,Project,";
		foreach ($dates as $dateIndex => $curdate) {
			$header= $header.$curdate.",";
		}
		fwrite($fh, $header."\n");
		$rrt_write_data = "";
		//For each user and date
		//
		foreach ($userList as $userIndex => $user) {
			
			$rrtProjects = $this->getUserRrtProjects($user['User']['id'],$io_controller);

			foreach ($rrtProjects as $projectIndex => $rrtProject) {
				
				$rrt_write_data = $user['User']['full_name'].","."Rrt".",".$rrtProject['v_rrt_efforts']['project_name'].",";

				foreach ($dates as $dateIndex => $curdate) {
				
					//Get the Change effort
					//
					$rrtEffort = $this->getRrtEffort($user['User']['id'],$curdate,$rrtProject['v_rrt_efforts']['rrt_id'],$io_controller);
					$rrt_write_data = $rrt_write_data.$rrtEffort.",";
				}
				fwrite($fh, $rrt_write_data."\n");


			}

		}

		fclose($fh);
	}


	public function downloadReport($i_data,&$io_controller)
	{
		if($i_data['stream'] == "incident")
		{
			$this->downloadIncidentReport($i_data,$io_controller);
		}
		if($i_data['stream'] == "ttrs")
		{
			$this->downloadTtrsReport($i_data,$io_controller);
		}
		if($i_data['stream'] == "task")
		{
			$this->downloadTaskReport($i_data,$io_controller);
		}
		if($i_data['stream'] == "problem")
		{
			$this->downloadProblemReport($i_data,$io_controller);
		}
		if($i_data['stream'] == "change")
		{
			$this->downloadProblemReport($i_data,$io_controller);
		}
		if($i_data['stream'] == "rrt")
		{
			$this->downloadRrtReport($i_data,$io_controller);
		}										
	}

	public function compliance($i_data,&$io_controller)
	{
		//$i_data['start_date']='2015-01-02';
		//$i_data['end_date']='2015-01-02';
		//Get the date range
		//
		$dates = $this->getDateRange($i_data['start_date'],$i_data['end_date']);

		$dataVal = array();

		$rotaArray = array();

		$userefforts= $this->getEffortForAll($i_data['start_date'],$i_data['end_date'],$io_controller);

		//Get the list of service managers
		//
		$serviceManagers = $this->getServiceManagers($io_controller);

		foreach ($serviceManagers as $index => $serviceManager) {

			$smId = $serviceManager['emp_id'];
			$smName = $serviceManager['full_name'];
			
			$dataVal[$smId]=array('emp_id'=>$smId,'full_name'=>$smName,'leads'=>array());

			//Get the list of service leads
			//
			$serviceLeads = $this->getServiceLeads($smId,$io_controller);

			foreach ($serviceLeads as $slIndex => $serviceLead) {

				$slId = $serviceLead['emp_id'];
				$slName = $serviceLead['full_name'];

				$dataVal[$smId]['leads'][$slId]=array('emp_id'=>$slId,'full_name'=>$slName,'users'=>array());

				//Get the list of users
				//
				$analysts = $this->getServiceAnalysts($slId,$io_controller);

				foreach ($analysts as $analystIndex => $analyst) {
					$analystEmpId = $analyst['emp_id'];
					$analystId = $analyst['user_id'];
					$analystName = $analyst['full_name'];
					$effortArray = array();
					$found = false;
					foreach ($serviceManagers as $smIndex => $sm) {
						if($sm['emp_id'] == $analystEmpId)
						{
							$found = true;
						}
					}

					if($found == false)
					{
						$rotaArray[$analystEmpId] = $this->getRota($analystId,$i_data['start_date'],$i_data['end_date'],$io_controller);

						if(!isset($userefforts[$analystId]))
						{
							$userefforts[$analystId]=array();
						}
						foreach ($dates as $dateIndex => $curdate) {
							if(!isset($userefforts[$analystId][$curdate]))
							{
								$userefforts[$analystId][$curdate] = 0;
							}
						}							
						$effort = array();
						foreach ($userefforts[$analystId] as $key => $value) {
							$effort[$key]=array('date'=>$key,'effort'=>$value);
						}
						$dataVal[$smId]['leads'][$slId]['users'][$analystEmpId]=array('emp_id'=>$analystEmpId,'full_name'=>$analystName,'dates'=>$effort);							

					}

				}
			}
		}
		
		
		foreach ($dates as $dateIndex => $curdate) {
			foreach ($dataVal as $mngrIndex => &$mngr) {
				$mngr[$curdate]=array();
				$mngr[$curdate]['totalEmpIds']=array();
				$mngr[$curdate]['nonZeroEmpIds']=array();
				foreach ($mngr['leads'] as $leadIndex => &$lead) {
					$lead[$curdate]=array();
					$lead[$curdate]['totalEmpIds']=array();
					$lead[$curdate]['nonZeroEmpIds']=array();

					foreach ($lead['users'] as $userIndex => &$user)
					{
						array_push($lead[$curdate]['totalEmpIds'], $user['emp_id']);
						array_push($mngr[$curdate]['totalEmpIds'], $user['emp_id']);

						foreach ($user['dates'] as $dateIndex => &$date) {
							if($curdate == $date['date'])
							{
								if(isset($rotaArray[$user['emp_id']][$curdate]) && in_array($rotaArray[$user['emp_id']][$curdate],array("P","L","C","Z","T"),true))
								{
									$date['effort_s']=$rotaArray[$user['emp_id']][$curdate];
									$date['effort']=$rotaArray[$user['emp_id']][$curdate];
								}
								else
								{
									$date['effort_s']=gmdate("d days H:i:s", (int)$date['effort']);
								}
								
								if($date['effort'] != 0 || in_array($date['effort'],array("P","L","C","Z","T"),true))
								{
									array_push($lead[$curdate]['nonZeroEmpIds'], $user['emp_id']);
									array_push($mngr[$curdate]['nonZeroEmpIds'], $user['emp_id']);
								}								
							}
						}
					}
					$lead[$curdate]['totalEmpIds'] = array_map("unserialize", array_unique(array_map("serialize", $lead[$curdate]['totalEmpIds'])));
					$lead[$curdate]['nonZeroEmpIds'] = array_map("unserialize", array_unique(array_map("serialize", $lead[$curdate]['nonZeroEmpIds'])));
					$lead[$curdate]['totalUsers'] = count($lead[$curdate]['totalEmpIds']);
					$lead[$curdate]['nzUsers'] = count($lead[$curdate]['nonZeroEmpIds']);
					$lead[$curdate]['percent'] = 0;
					if($lead[$curdate]['nzUsers'] != 0)
					{
						$lead[$curdate]['percent'] = sprintf('%0.2f', ($lead[$curdate]['nzUsers']*100)/$lead[$curdate]['totalUsers']);
					}
				}
				$mngr[$curdate]['totalEmpIds'] = array_map("unserialize", array_unique(array_map("serialize", $mngr[$curdate]['totalEmpIds'])));
				$mngr[$curdate]['nonZeroEmpIds'] = array_map("unserialize", array_unique(array_map("serialize", $mngr[$curdate]['nonZeroEmpIds'])));

				$mngr[$curdate]['totalUsers'] = count($mngr[$curdate]['totalEmpIds']);
				$mngr[$curdate]['nzUsers'] = count($mngr[$curdate]['nonZeroEmpIds']);
				$mngr[$curdate]['percent'] = 0;
				if($mngr[$curdate]['nzUsers'] != 0)
				{
					$mngr[$curdate]['percent'] = sprintf('%0.2f', ($mngr[$curdate]['nzUsers']*100)/$mngr[$curdate]['totalUsers']);
				}
			}
		}

		$this->exportComplianceToCsv($dataVal,$dates);

		return array('dates'=>$dates,'value'=>$dataVal);

	}

	public function exportComplianceToCsv($data,$dates)
	{
		$fh = fopen("compliance_report.csv","w+");

		$header = "ServiceManager,ServiceLead,User,";
		foreach ($dates as $dateIndex => $curdate) {
			$header= $header.$curdate.",";
		}
		fwrite($fh, $header."\n");
		$writeData = "";
		$userDictionary = array();
		foreach ($data as $mngrIndex => $mngr) {
			
			foreach ($mngr['leads'] as $leadIndex => $lead) {
				
				foreach ($lead['users'] as $userIndex => $user) {
					if(!isset($userDictionary[$user['emp_id']]))
					{
						$userDictionary[$user['emp_id']]=1;
						$writeData = $mngr['full_name'].",".$lead['full_name'].",".$user['full_name'];
						foreach ($user['dates'] as $dateIndex => $date) {
							$writeData= $writeData.",".$date['effort_s'];
						}
						fwrite($fh, $writeData."\n");
					}
				}
			}
		}
		fclose($fh);
	}


	public function getDateRange($start_date,$end_date)
	{
		//Get the date range and store it in a array
		//
		$dates = array();
		$start_date = new DateTime($start_date);
		$end_date = new DateTime($end_date);
		$curdate = $start_date;
		while(($end_date->getTimestamp() - $curdate->getTimestamp()) > 0) { 
			$str = $curdate->format('Y-m-d');
			array_push($dates,$str);
			$curdate->add(new DateInterval("P1D"));
		}
		$str = $end_date->format('Y-m-d');
		array_push($dates,$str);		
		return $dates;		
	}

	public function getEffortForAllStreams($user_id,$start_date,$end_date,&$io_controller)
	{
		$totalSeconds = 0;
		$dates = $this->getDateRange($start_date,$end_date);
		//Get the users Incident effort
		//
		$incidentSeconds = $this->getIncidentEffort($user_id,$start_date,$end_date,"","",$io_controller);

		//Get the users Ttrs effort
		//
		$ttrsSeconds = $this->getTtrsEffort($user_id,$start_date,$end_date,"","",$io_controller);

		//Get the users Problem effort
		//
		$problemSeconds = $this->getProblemEffort($user_id,$start_date,$end_date,"","",$io_controller);

		//Get the users Change effort
		//
		$changeSeconds = $this->getChangeEffort($user_id,$start_date,$end_date,"","",$io_controller);

		//Get the users RRT effort
		//
		$rrtSeconds = $this->getRrtEffort($user_id,$start_date,$end_date,"","",$io_controller);

		//Get the users Task effort
		//
		$taskSeconds = $this->getTaskEffort($user_id,$start_date,$end_date,"","",$io_controller);

		$totalSeconds = array();
		foreach ($dates as $dateIndex => $curdate) {
			array_push($totalSeconds,array('date'=>$curdate,'effort'=>$incidentSeconds[$curdate] + $ttrsSeconds[$curdate] + $problemSeconds[$curdate] + $changeSeconds[$curdate] + $rrtSeconds[$curdate] + $taskSeconds[$curdate]));
		}

		return $totalSeconds;
	}

	/* Functions for all streams */

	public function getServiceManagers(&$io_controller)
	{
		$managers = array();
		//Get the users Incident service managers
		//
		$incidentManagers = $this->getIncidentServiceManagers($io_controller);
		$managers = array_merge_recursive($managers,$incidentManagers);

		//Get the users Ttrs service managers
		//
		$ttrsManagers = $this->getTtrsServiceManagers($io_controller);
		$managers = array_merge_recursive($managers,$ttrsManagers);

		//Get the users Problem service managers
		//

		//Get the users Change service managers
		//

		//Get the users RRT service managers
		//
		$rrtManagers = $this->getRrtServiceManagers($io_controller);
		$managers = array_merge_recursive($managers,$rrtManagers);

		//Get the users Task service managers
		//	
		$managers = array_map("unserialize", array_unique(array_map("serialize", $managers)));

		return $managers;
	}

	public function getServiceLeads($serviceMangerId,&$io_controller)
	{
		$leads = array();

		//Get the users Incident service leads
		//
		$incidentLeads = $this->getIncidentServiceLeads($serviceMangerId,$io_controller);
		
		$leads = array_merge_recursive($leads,$incidentLeads);

		//Get the users Ttrs service leads
		//
		$ttrsLeads = $this->getTtrsServiceLeads($serviceMangerId,$io_controller);
		
		$leads = array_merge_recursive($leads,$ttrsLeads);

		//Get the users Problem service leads
		//

		//Get the users Change service leads
		//

		//Get the users RRT service leads
		//
		$rrtLeads = $this->getRrtServiceLeads($serviceMangerId,$io_controller);
		
		$leads = array_merge_recursive($leads,$rrtLeads);
		//Get the users Task service leads
		//
		$leads = array_map("unserialize", array_unique(array_map("serialize", $leads)));
		return $leads;
	}

	public function getServiceAnalysts($serviceLeadId,&$io_controller)
	{
		$analysts = array();

		//Get the users Incident service leads
		//
		$incidentAnalysts = $this->getIncidentServiceAnalysts($serviceLeadId,$io_controller);
		$analysts = array_merge_recursive($analysts,$incidentAnalysts);

		//Get the users Ttrs service analysts
		//
		$ttrsAnalysts = $this->getTtrsServiceAnalysts($serviceLeadId,$io_controller);
		$analysts = array_merge_recursive($analysts,$ttrsAnalysts);

		//Get the users Problem service leads
		//

		//Get the users Change service leads
		//

		//Get the users RRT service leads
		//
		$rrtAnalysts = $this->getRrtServiceAnalysts($serviceLeadId,$io_controller);
		$analysts = array_merge_recursive($analysts,$rrtAnalysts);
		//Get the users Task service leads
		//
		$analysts = array_map("unserialize", array_unique(array_map("serialize", $analysts)));
		return $analysts;
	}
	/* --Functions for all streams */


	public function getIncidentServiceManagerForUser($user_id,&$io_controller)
	{
		$incidentBuckets = $this->getUserIncidentBuckets($user_id,$io_controller);

		$serviceManagers = array();
		//For every incident bucket assigned to user
		//
		foreach ($incidentBuckets as $bucketIndex => $bucket) {
			
			//Get the list of service leads
			//
			$mngrs = $this->getIncidentServiceManagerForBucket($bucket['VIncidentBucketUser']['bucket_id'],$io_controller);

			$serviceManagers = array_merge_recursive($serviceManagers,$mngrs);
		}

		//Remove duplicates
		//
		$serviceManagers = array_map("unserialize", array_unique(array_map("serialize", $serviceManagers)));
		return $serviceManagers;
	}


	public function getIncidentServiceManagers(&$io_controller)
	{
		$io_controller->loadModel('VIncidentBucketUser');
		//Get the list of service leads
		//
		$serviceManagers = $io_controller->VIncidentBucketUser->find('all',array(
			'conditions'=>array(
				'role_name'=>'Service Manager'
				),
			'fields'=>array('full_name','emp_id'),
			'group' => array('emp_id')
			)
		);
		$retVal = array();
		foreach ($serviceManagers as $mngrIndex => $mngr) {
			array_push($retVal, $mngr['VIncidentBucketUser']);
		}

		return $retVal;
	}

	public function getTtrsServiceManagers(&$io_controller)
	{
		$io_controller->loadModel('VTtrsBucketUser');
		//Get the list of service leads
		//
		$serviceManagers = $io_controller->VTtrsBucketUser->find('all',array(
			'conditions'=>array(
				'role_name'=>'Service Manager'
				),
			'fields'=>array('full_name','emp_id'),
			'group' => array('emp_id')
			)
		);
		$retVal = array();
		foreach ($serviceManagers as $mngrIndex => $mngr) {
			array_push($retVal, $mngr['VTtrsBucketUser']);
		}		
		return $retVal;
	}

	public function getRrtServiceManagers(&$io_controller)
	{
		$io_controller->loadModel('VRrtUserBucket');
		//Get the list of service leads
		//
		$serviceManagers = $io_controller->VRrtUserBucket->find('all',array(
			'conditions'=>array(
				'role_name LIKE'=>'%Test Manager%'
				),
			'fields'=>array('full_name','emp_id'),
			'group' => array('emp_id')
			)
		);
		$retVal = array();
		foreach ($serviceManagers as $mngrIndex => $mngr) {
			array_push($retVal, $mngr['VRrtUserBucket']);
		}		
		return $retVal;
	}

	public function getIncidentServiceLeads($serviceMangerId,&$io_controller)
	{
		$io_controller->loadModel('VIncidentBucketUser');

		//Get the list of buckets for the serviceManager
		//
		$serviceManagerBuckets = $io_controller->VIncidentBucketUser->find('list',array(
			'conditions'=>array(
				'emp_id'=>$serviceMangerId
				),
			'fields'=>array('bucket_id')
			)
		);

		$bucket_ids = array_values($serviceManagerBuckets);

		//Get the list of buckets for the serviceManager
		//
		$serviceLeads = $io_controller->VIncidentBucketUser->find('all',array(
			'conditions'=>array(
				'role_name LIKE'=>'%Service Lead%',
				'bucket_id'=>$bucket_ids
				),
			'fields'=>array('emp_id','full_name'),
			'group' => array('emp_id')
			)
		);
		$retVal = array();
		foreach ($serviceLeads as $leadIndex => $lead) {
			array_push($retVal, $lead['VIncidentBucketUser']);
		}		
		return $retVal;
	}

	public function getTtrsServiceLeads($serviceMangerId,&$io_controller)
	{
		$io_controller->loadModel('VTtrsBucketUser');

		//Get the list of buckets for the serviceManager
		//
		$serviceManagerBuckets = $io_controller->VTtrsBucketUser->find('list',array(
			'conditions'=>array(
				'emp_id'=>$serviceMangerId
				),
			'fields'=>array('bucket_id')
			)
		);

		$bucket_ids = array_values($serviceManagerBuckets);

		//Get the list of buckets for the serviceManager
		//
		$serviceLeads = $io_controller->VTtrsBucketUser->find('all',array(
			'conditions'=>array(
				'role_name LIKE'=>'%Service Lead%',
				'bucket_id'=>$bucket_ids
				),
			'fields'=>array('emp_id','full_name'),
			'group' => array('emp_id')
			)
		);
		$retVal = array();
		foreach ($serviceLeads as $leadIndex => $lead) {
			array_push($retVal, $lead['VTtrsBucketUser']);
		}		
		return $retVal;

	}

	public function getRrtServiceLeads($serviceMangerId,&$io_controller)
	{
		$io_controller->loadModel('VRrtUserBucket');

		//Get the list of buckets for the serviceManager
		//
		$serviceManagerBuckets = $io_controller->VRrtUserBucket->find('list',array(
			'conditions'=>array(
				'emp_id'=>$serviceMangerId
				),
			'fields'=>array('bucket_id')
			)
		);

		$bucket_ids = array_values($serviceManagerBuckets);

		//Get the list of buckets for the serviceManager
		//
		$serviceLeads = $io_controller->VRrtUserBucket->find('all',array(
			'conditions'=>array(
				'role_name LIKE'=>'%Test Lead%',
				'bucket_id'=>$bucket_ids
				),
			'fields'=>array('emp_id','full_name'),
			'group' => array('emp_id')
			)
		);
		$retVal = array();
		foreach ($serviceLeads as $leadIndex => $lead) {
			array_push($retVal, $lead['VRrtUserBucket']);
		}		
		return $retVal;

	}

	public function getIncidentServiceAnalysts($serviceLeadId,&$io_controller)
	{
		$io_controller->loadModel('VIncidentBucketUser');

		//Get the list of buckets for the serviceManager
		//
		$serviceLeadBuckets = $io_controller->VIncidentBucketUser->find('list',array(
			'conditions'=>array(
				'emp_id'=>$serviceLeadId
				),
			'fields'=>array('bucket_id')
			)
		);

		$bucket_ids = array_values($serviceLeadBuckets);

		//Get the list of buckets for the serviceManager
		//
		$serviceAnalysts = $io_controller->VIncidentBucketUser->find('all',array(
			'conditions'=>array(
				'bucket_id'=>$bucket_ids,
				'status_id'=>'1'
				),
			'fields'=>array('user_id','emp_id','full_name'),
			'group' => array('emp_id')
			)
		);

		$retVal = array();
		foreach ($serviceAnalysts as $leadIndex => $lead) {
			if($this->isUserActive($lead['VIncidentBucketUser']['user_id'],$io_controller))
			{
				array_push($retVal, $lead['VIncidentBucketUser']);
			}			
			
		}		
		return $retVal;
	}

	public function getTtrsServiceAnalysts($serviceLeadId,&$io_controller)
	{
		$io_controller->loadModel('VTtrsBucketUser');

		//Get the list of buckets for the serviceManager
		//
		$serviceLeadBuckets = $io_controller->VTtrsBucketUser->find('list',array(
			'conditions'=>array(
				'emp_id'=>$serviceLeadId
				),
			'fields'=>array('bucket_id')
			)
		);

		$bucket_ids = array_values($serviceLeadBuckets);

		//Get the list of buckets for the serviceManager
		//
		$serviceAnalysts = $io_controller->VTtrsBucketUser->find('all',array(
			'conditions'=>array(
				'bucket_id'=>$bucket_ids
				),
			'fields'=>array('user_id','emp_id','full_name'),
			'group' => array('emp_id')
			)
		);
		$retVal = array();
		foreach ($serviceAnalysts as $leadIndex => $lead) {
			if($this->isUserActive($lead['VTtrsBucketUser']['user_id'],$io_controller))
			{
				array_push($retVal, $lead['VTtrsBucketUser']);
			}
		}
		return $retVal;
	}

	public function getRrtServiceAnalysts($serviceLeadId,&$io_controller)
	{
		$io_controller->loadModel('VRrtUserBucket');

		//Get the list of buckets for the serviceManager
		//
		$serviceLeadBuckets = $io_controller->VRrtUserBucket->find('list',array(
			'conditions'=>array(
				'emp_id'=>$serviceLeadId
				),
			'fields'=>array('bucket_id')
			)
		);

		$bucket_ids = array_values($serviceLeadBuckets);

		//Get the list of buckets for the serviceManager
		//
		$serviceAnalysts = $io_controller->VRrtUserBucket->find('all',array(
			'conditions'=>array(
				'bucket_id'=>$bucket_ids
				),
			'fields'=>array('user_id','emp_id','full_name'),
			'group' => array('emp_id')
			)
		);
		$retVal = array();
		foreach ($serviceAnalysts as $leadIndex => $lead) {
			if($this->isUserActive($lead['VRrtUserBucket']['user_id'],$io_controller))
			{
				array_push($retVal, $lead['VRrtUserBucket']);
			}
			
		}
		return $retVal;
	}

	public function getIncidentServiceManagerForBucket($bucket_id,&$io_controller)
	{
		$io_controller->loadModel('VIncidentBucketUser');
		//Get the list of service leads
		//
		$serviceManagers = $io_controller->VIncidentBucketUser->find('all',array(
			'conditions'=>array(
				'role_name'=>'Service Manager',
				'bucket_id'=>$bucket_id
				),
			'fields'=>array('full_name','emp_id')
			)
		);
		
		return $serviceManagers;
	}

	public function getIncidentServiceLeadForUser($user_id,&$io_controller)
	{
		$incidentBuckets = $this->getUserIncidentBuckets($user_id,$io_controller);

		$serviceLeads = array();
		//For every incident bucket assigned to user
		//
		foreach ($incidentBuckets as $bucketIndex => $bucket) {
			
			//Get the list of service leads
			//
			$leads = $this->getIncidentServiceLeadForBucket($bucket['VIncidentBucketUser']['bucket_id'],$io_controller);

			$serviceLeads = array_merge($serviceLeads,$leads);
		}

		//Remove duplicates
		//
		$serviceLeads = array_map("unserialize", array_unique(array_map("serialize", $serviceLeads)));
		return $serviceLeads;
	}

	public function getIncidentServiceLeadForBucket($bucket_id,&$io_controller)
	{
		$io_controller->loadModel('VIncidentBucketUser');
		//Get the list of service leads
		//
		$serviceLeads = $io_controller->VIncidentBucketUser->find('all',array(
			'conditions'=>array(
				'role_name LIKE'=>'%Service Lead%',
				'bucket_id'=>$bucket_id
				),
			'fields'=>array('full_name','emp_id')
			)
		);

		return $serviceLeads;
	}

	public function isUserActive($user_id,&$io_controller)
	{
		$io_controller->loadModel('User');
		$user=array();
		$user = $io_controller->User->find('first',array('conditions'=>array('User.id'=>$user_id,'User.status'=>'1')));
		if(isset($user['User']))
		{
			return true;
		}
		return false;
	}

	public function getRota($user_id,$start_date,$end_date,&$io_controller)
    {
        $io_controller->loadModel('Rota');
        $rota_query="select DATE(date) as date,rota from rotas `Rota` where `Rota`.user_id=? and `Rota`.date between ? and ?";
        $result =  $io_controller->Rota->query($rota_query,array($user_id,$start_date." 00:00:00",$end_date." 00:00:00"));
        $retArray = array();
        foreach ($result as $key => $value) {
        	$retArray[$value['0']['date']] = $value['Rota']['rota'];
        }
        return $retArray;
    }

	public function l15Compliance($i_data,&$io_controller)
	{
		//$i_data['start_date']='2015-01-02';
		//$i_data['end_date']='2015-01-02';
		//Get the date range
		//
		$dates = $this->getDateRange($i_data['start_date'],$i_data['end_date']);

		$dataVal = array('sm'=>array('users'=>array(),'full_name'=>"L15 Team","emp_id"=>"1234"));

		$rotaArray = array();

		$userefforts= $this->getEffortForAll($i_data['start_date'],$i_data['end_date'],$io_controller);

		//Get the list of users
		//
		$analysts = $this->getL15ServiceAnalysts($io_controller);

		foreach ($analysts as $analystIndex => $analyst) {
			$analystEmpId = $analyst['VL15User']['emp_id'];
			$analystId = $analyst['VL15User']['id'];
			$analystName = $analyst['VL15User']['full_name'];
			$effortArray = array();
			$rotaArray[$analystEmpId] = $this->getRota($analystId,$i_data['start_date'],$i_data['end_date'],$io_controller);

			if(!isset($userefforts[$analystId]))
			{
				$userefforts[$analystId]=array();
			}
			foreach ($dates as $dateIndex => $curdate) {
				if(!isset($userefforts[$analystId][$curdate]))
				{
					$userefforts[$analystId][$curdate] = 0;
				}
			}							
			$effort = array();
			foreach ($userefforts[$analystId] as $key => $value) {
				$effort[$key]=array('date'=>$key,'effort'=>$value);
			}
			$dataVal['sm']['users'][$analystEmpId]=array('emp_id'=>$analystEmpId,'full_name'=>$analystName,'dates'=>$effort);
		}
		
		
		foreach ($dates as $dateIndex => $curdate) {
			foreach ($dataVal as $mngrIndex => &$mngr) {
				$mngr[$curdate]=array();
				$mngr[$curdate]['totalEmpIds']=array();
				$mngr[$curdate]['nonZeroEmpIds']=array();

				foreach ($mngr['users'] as $userIndex => &$user)
				{
					array_push($mngr[$curdate]['totalEmpIds'], $user['emp_id']);

					foreach ($user['dates'] as $dateIndex => &$date) {
						if($curdate == $date['date']){
							if(isset($rotaArray[$user['emp_id']][$curdate]) && in_array($rotaArray[$user['emp_id']][$curdate],array("P","L","C","Z","T"),true))
							{
								$io_controller->logMsg('info',print_r($rotaArray,true));
								$date['effort_s']=$rotaArray[$user['emp_id']][$curdate];
								$date['effort']=$rotaArray[$user['emp_id']][$curdate];
							}
							else
							{
								$date['effort_s']=gmdate("H:i:s", (int)$date['effort']);
							}
							
							if($date['effort'] != 0 || in_array($date['effort'],array("P","L","C","Z","T"),true))
							{
								array_push($mngr[$curdate]['nonZeroEmpIds'], $user['emp_id']);
							}
						}
					}
				}

				$mngr[$curdate]['totalEmpIds'] = array_map("unserialize", array_unique(array_map("serialize", $mngr[$curdate]['totalEmpIds'])));
				$mngr[$curdate]['nonZeroEmpIds'] = array_map("unserialize", array_unique(array_map("serialize", $mngr[$curdate]['nonZeroEmpIds'])));

				$mngr[$curdate]['totalUsers'] = count($mngr[$curdate]['totalEmpIds']);
				$mngr[$curdate]['nzUsers'] = count($mngr[$curdate]['nonZeroEmpIds']);
				$mngr[$curdate]['percent'] = 0;
				if($mngr[$curdate]['nzUsers'] != 0)
				{
					$mngr[$curdate]['percent'] = sprintf('%0.2f', ($mngr[$curdate]['nzUsers']*100)/$mngr[$curdate]['totalUsers']);
				}
			}
		}

//		$this->exportComplianceToCsv($dataVal,$dates);

		return array('dates'=>$dates,'value'=>$dataVal);

	}
	
   
    public function getL15ServiceAnalysts(&$io_controller)
    {
    	$io_controller->loadModel('VL15User');
    	$analysts = $io_controller->VL15User->find('all',array('conditions'=>array(
    			'group_name'=>'L15'
    		)));
		return $analysts;
    }

    public function updateEffort($i_data,&$io_controller)
    {

    	$effort=$io_controller->IncidentEffort->findById($i_data['id']);
    	if(!isset($effort['IncidentEffort']))
    	{
    		throw new Exception("No Efforts to update", ErrorCodes::$REQUEST_INVALID_DATA);
    		
    	}
    	$io_controller->IncidentEffort->id=$i_data['id'];
    	$io_controller->IncidentEffort->save(array("start_time"=>$i_data['start_time'],"end_time"=>$i_data['end_time']));

    }

}