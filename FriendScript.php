<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use App\Model\BeetSendInvitation;
use App\Model\BeetUserSetting;
use App\Model\BeetFriends;
use App\Model\UserActivity;
use App\Model\Zipcode;
use Mail;
use Auth;
use App\Model\BeetEmailTemplate;


class FriendScrippt extends Controller
{
    public function friend_script_auto()
    {
    	// Verify already friend or not
    	$row = BeetSendInvitation::whereNotNull('register_id')->get();
    	foreach ($row as $rs) 
    	{
    		$uid = $rs->invited_by;
    		$agent_id = $rs->register_id;
    		$check_friend = BeetFriends::where([["user_id",$uid],["friend_id",$agent_id],["deleted",0]])->orWhere([["user_id",$agent_id],["friend_id",$uid],["deleted",0]])->orderBy("id","DESC")->get()->first();
    		if(sizeof($check_friend) == 0)
    		{
    			 $accepted_date = $rs->updated_at;
			     $stmf = new BeetFriends();
		       $stmf->user_id 	 = $uid;
		       $stmf->friend_id  = $agent_id;
		       $stmf->req_status = "Active";
		       $stmf->created_at = $accepted_date;
		       $stmf->updated_at = $accepted_date;
		       $stmf->save(); 
		       $action_table_id = $stmf->id;
		       $agent_name = implode(" ",User::where("id",$agent_id)->selectRaw("firstname,lastname")->get()->first()->toArray());

		       $agent_name_u	= User::where("id",$agent_id)->get()->first();
           $fUser = User::where("id",$uid)->get()->first();

           $msgg_f = "<a href='https://home.google.com/".agent_array("","yes",$agent_name_u->user_type)."/agent/".strtolower($agent_name)."/$agent_id'>$agent_name accepted your invitation. Click here to see profile.</a>";
           $msgg_f1 = "<a href='https://home.google.com/message?type=friend'>You are now friend with ".$agent_name."</a>";
           $msgg_t = "<a href='https://home.google.com/message?type=friend'>You are now friend with ".$fUser->firstname." ".$fUser->lastname."</a>";
           $this->InsertUserActivity($uid,"sent_request","send_request","beet_friends",$action_table_id,$uid,"",$msgg_f,$accepted_date);
           $this->InsertUserActivity($uid,"sent_request","send_request","beet_friends",$action_table_id,$uid,"",$msgg_f1,$accepted_date);
           $this->InsertUserActivity($agent_id,"accepted_request","send_request","beet_friends",$action_table_id,$uid,"",$msgg_t,$accepted_date);	
				}

    	}
    	
    }


   public function confirm_your_frnd($type,$sender_id,$friend_id)
   {
      $agent_id  = base64_decode($sender_id);
      $uid       = base64_decode($friend_id);
      $SND = User::where("id",$agent_id)->get()->first();
      $FRD = User::where("id",$uid)->get()->first();
       //Login of if valid user
      try{
         if(sizeof($FRD) > 0)
         {
          Auth::loginUsingId($uid,true);  
          setAgentCookie($uid);
         }
      }
      catch(\Exception $ex){}

      $agent_name = implode(" ",User::where("id",$agent_id)->selectRaw("firstname,lastname")->get()->first()->toArray());
      $check_friend = BeetFriends::where([["user_id",$uid],["friend_id",$agent_id]])->orWhere([["user_id",$agent_id],["friend_id",$uid]])->where("deleted",0)->get()->first();
    
      if(sizeof($check_friend)>0)
      {
        if($type=="accept")
        {
          $status = "Active";
          $deleted = 0;
          $type = "accepted";
        }
        else
        {
          $status = "Requested";
          $deleted=1;
          $type = "declined";
        }

         if($check_friend->req_status!="Active")
         {
           $id = $check_friend->id;
           $stm = BeetFriends::find($id);
           $stm->req_status = $status;
           $stm->deleted = $deleted;
           $stm->save();

           $addFriendMessage = "Hi ".$FRD->firstname." ".$FRD->lastname."<br><br>You have <strong>$type</strong> friend request with <strong>$agent_name.</strong>";  

           $rsCheckActivity = UserActivity::where([["user_id",$uid],["action_by_id",$agent_id],["action_type","accept_request"]])->get()->first();

           if(sizeof($rsCheckActivity)>0)
           {
            
              UserActivity::where("id",$rsCheckActivity->id)->update(["action_type"=>"accepted_request"]);
            
           }

           $this->InsertUserActivity($uid,"confirmed_request","send_request","beet_friends",$id,$uid,"","You have $type friend request with $agent_name");
           $this->InsertUserActivity($agent_id,"confirmed_request","send_request","beet_friends",$id,$uid,"",$FRD->firstname." ".$FRD->lastname." has $type your friend request");

         
          $template = \App\Model\BeetEmailTemplate::getEmail("send_email_to_friend");
          if($template)
          {
            $email_subject  = stripslashes($template->email_subject);
            $email_content  = stripslashes($template->email_content);
            $layout_header  = stripslashes($template->layout_header);
            $layout_footer  = stripslashes($template->layout_footer);
            $FRIEND_URL = BEET_URL.agent_array("","yes",$FRD->user_type)."/agent/".$FRD->firstname."-".$FRD->lastname."/".$uid;

            $email_subject  = str_replace("{AGENT_NAME}", $SND->firstname, $email_subject);
            $email_subject  = str_replace("{FRIEND_NAME}", $FRD->firstname." ".$FRD->lastname, $email_subject);
            $email_subject  = str_replace("{ACTION}", $type, $email_subject);
            $email_subject  = str_replace("{FRIEND_FULL_NAME}", $FRD->firstname.' '.$FRD->lastname, $email_subject);

            
            $email_content  = str_replace("{AGENT_NAME}", $SND->firstname, $email_content);
            $email_content  = str_replace("{FRIEND_NAME}", $FRD->firstname, $email_content);
            $email_content  = str_replace("{FRIEND_FULL_NAME}", $FRD->firstname.' '.$FRD->lastname, $email_content);
            $email_content  = str_replace("{FRIEND_ID}", $uid, $email_content);
            $email_content  = str_replace("{AGENT_ID}", $agent_id, $email_content);
            $email_content  = str_replace("{FRIEND_URL}", $FRIEND_URL, $email_content);

            $layout_footer  = str_replace("{UNSUBSCRIBE_EMAIL}",base64_encode($FRD->email),$layout_footer);

            $data =  [
              'subject' => $email_subject,
              'to'      => $SND->email,//$form1['email']
              'html'    => true,
            ];
            $message = $layout_header;
            $message.= $email_content;
            $message.= $layout_footer;

            try
            {
            
              Mail::send(array(), $data, function ($msg) use ($message,$data)
              {
                $msg->to($data['to'])->subject($data['subject'])
                ->setBody($message, 'text/html');
                $msg->from('admin@admin.com','Admin');
              });
            
            }
            catch(\Exception $e)
            {
             //echo $e->getMessage();
            }
          }
       }
       else
       {
           $addFriendMessage = "Hi ".$FRD->firstname." ".$FRD->lastname."<br><br>You are already <strong>confirmed</strong> friend request with <strong>$agent_name.</strong>";  
           return  redirect("agent/profile");
       }
      }
      else
      {    
          return  redirect("/profile");
            
      }   
      return view("request_confirmation")->with("addFriendMessage",$addFriendMessage);
   }


   public function cron_for_send_unread_message()
   {
         $SMessage = \DB::select(\DB::raw("SELECT beet_send_message.*,GROUP_CONCAT(message,' ') as msg FROM beet_send_message WHERE status = '1' AND send_mail ='0' GROUP BY (UNIX_TIMESTAMP(created_at) + 5) DIV 10 ,to_id,from_id ORDER BY to_id ASC "));
         
         $arr =[];
         $message ="";
         foreach ($SMessage as $rs) 
         {
            $to_id  = $rs->to_id;            
            $arr[$to_id."##".$rs->from_id][] = $rs;    
            \DB::table("beet_send_message")->where([["from_id",$rs->from_id],["to_id",$to_id]])->update(["send_mail"=>1,"status"=>0]);

         }

         foreach ($arr as $key => $value) 
         {
            $idd     = explode("##", $key);
            $from_id = $idd[1];
            $to_id   = $idd[0];

              $rtoken   = base64_encode($from_id."---".$to_id);
              $rsTo     = User::where("id",$to_id)->get()->first();
              $rsFrom   = User::where("id",$from_id)->get()->first();

              $template = BeetEmailTemplate::getEmail("send_message_notification");
              if($template)
              {
                $email_subject = $template->email_subject;
                $email_content = $template->email_content;
                $layout_header = $template->layout_header;
                $layout_footer = $template->layout_footer;
                try
                {
                      $email_content = str_replace("{AGENT_NAME}",$rsFrom->firstname.' '.$rsFrom->lastname,$email_content);
                      $email_content = str_replace("{AGENT_URL}",BEET_URL.agent_array("","yes",$rsFrom->user_type)."/agent/".strtolower($rsFrom->firstname."-".$rsFrom->lastname)."/".$rsFrom->id,$email_content);
                      $email_subject = str_replace("{AGENT_NAME}",$rsFrom->firstname,$email_subject);

                      $email_content = str_replace("{RECIPIENT_NAME}",$rsTo->firstname.' '.$rsTo->lastname,$email_content);
                      //$email_content = str_replace("{MESSAGE}",$msg,$email_content);
                      $email_content = str_replace("{TOKEN}",BEET_URL."message?type=message&rtoken=$rtoken",$email_content);
                      
                      $data = 
                      [
                        'subject' => $email_subject,                                  
                        'to'      => $rsTo->email,
                        'html'    => true,
                      ];
                     
                      $message = $layout_header;
                      $message.= $email_content;
                      $message.=$layout_footer;                
                      Mail::send(array(), $data, function ($msg) use ($message,$data) 
                      {
                        $msg->to($data['to'])->subject($data['subject'])
                        ->setBody($message, 'text/html');
                        $msg->from('admin@Admin.com','Admin');
                      });
 
                   }
                         
              }

         }

   }


   public function user_cron()
   {

      try
      {
      $row_users = \DB::select(\DB::raw("SELECT * FROM users WHERE user_zips <> ''  ORDER BY id ASC"));
      if(sizeof($row_users) > 0)
      {

          foreach ($row_users as $key => $value) 
          {
            try
            {
              $user_id  = $value->id;
              $utype    = $value->user_type;
              $ustatus  = $value->user_status;
              $upstatus = $value->profile_status;

              $user_zips = $value->user_zips;
              $user_zips_array = explode("|", $user_zips);
              if(sizeof($user_zips_array) > 0)
              {
                  foreach ($user_zips_array as $v)
                  {
                    if($v!="")
                    {
                      $data        = explode("-",$v);
                      if(sizeof($data) > 0)
                      {
                          $uzipcode    = trim(stripslashes($data[0]));
                          $state_city  = explode(",", $data[1]);
                          if(sizeof($state_city) > 0)
                          {
                            $ucity       = trim(stripslashes($state_city[0]));
                            $ustate      = trim(stripslashes($state_city[1]));
                            $chk = \DB::select(\DB::raw("SELECT * FROM user_locations WHERE uid='$user_id' AND uzipcode = '$uzipcode'"));
                            if(sizeof( $chk ) == 0)
                            {
                              $rowz = Zipcode::where("zipcode",$uzipcode)->get()->first();
                              $ulat = $rowz->zlat;
                              $ulng = $rowz->zlng;
                              \DB::select(\DB::raw("INSERT INTO user_locations SET uid = '$user_id', ulat = '$ulat' , ulng = '$ulng', ustate = '$ustate' , ucity = '$ucity' , uzipcode = '$uzipcode', utype ='$utype', ustatus ='$ustatus', upstatus ='$upstatus' "));  
                            }
                            else
                            {
                              \DB::select(\DB::raw("UPDATE user_locations SET utype ='$utype', ustatus ='$ustatus', upstatus ='$upstatus' WHERE uid =".$user_id));  
                            }  
                          }
                      }
                      
                                           
                    }
                  }  
              }
            }
            catch(\Exception $ex)
            {
              echo $ex->getMessage();
            }
        
          }  
      }
      
      }
      catch(\Exception $ex)
      {
        echo $ex->getMessage();
      }
   }

}
