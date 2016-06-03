<?php
/*
+-----------------------------------------------------------------------------+
| MobileQuiz ILIAS plug-in for audience feedback with mobile devices          |
+-----------------------------------------------------------------------------+
| Copyright 2016 Daniel Schoen                                                |
|                                                                             |
| MobileQuiz is free software: you can redistribute it and/or modify          |
| it under the terms of the GNU General Public License as published by        |
| the Free Software Foundation, either version 3 of the License, or           |
| (at your option) any later version.                                         |
|                                                                             |
|                                                                             |
| MobileQuiz is distributed in the hope that it will be useful,               |
| but WITHOUT ANY WARRANTY; without even the implied warranty of              |
| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the                |
| GNU General Public License for more details.                                |
|                                                                             |
| You should have received a copy of the GNU General Public License           |
| along with MobileQuiz.  If not, see <http://www.gnu.org/licenses/>.         |
+-----------------------------------------------------------------------------+
*/

include_once("./Services/Repository/classes/class.ilObjectPlugin.php");
include_once("./Customizing/global/plugins/Services/Repository/RepositoryObject/MobileQuiz/lib/phpqrcode/phpqrcode.php");
include_once("./Customizing/global/plugins/Services/Repository/RepositoryObject/MobileQuiz/lib/pChart/class/pData.class.php");
include_once("./Customizing/global/plugins/Services/Repository/RepositoryObject/MobileQuiz/lib/pChart/class/pDraw.class.php");
include_once("./Customizing/global/plugins/Services/Repository/RepositoryObject/MobileQuiz/lib/pChart/class/pImage.class.php");
include_once("./Customizing/global/plugins/Services/Repository/RepositoryObject/MobileQuiz/lib/pChart/class/pScatter.class.php");


/**
 * Application class for MobileQuiz repository object.
 *
 * @author Stephan Schulz <stschulz@mail.uni-mannheim.de>
 *
 * $Id$
 */
class ilObjMobileQuiz extends ilObjectPlugin
{
    /**
     * Constructor
     *
     * @access	public
     */
    function __construct($a_ref_id = 0){
        parent::__construct($a_ref_id);
    }

    /**
     * Get type.
     */
    final function initType(){
        $this->setType("xuiz");
    }

    /**
     * Create object
     */
    function doCreate(){
        global $ilDB;

        $affected_rows = $ilDB->manipulate("INSERT INTO rep_robj_xuiz_quizzes (quiz_id, name) VALUES (".$ilDB->quote($this->getId(), "integer").",".$ilDB->quote($this->getTitle(), "text").")");
    }

    /**
     * Read data from db
     */
    function doRead(){
        global $ilDB;

        $set = $ilDB->query("SELECT * FROM rep_robj_xuiz_quizzes ".
                " WHERE quiz_id = ".$ilDB->quote($this->getId(), "integer")
        );
        while ($rec = $ilDB->fetchAssoc($set)){
            $this->setTitle($rec["name"]);
        }
    }

    /**
     * Update data
     *
     */
    function doUpdate(){
        global $ilDB;

        $ilDB->manipulate($up = "UPDATE rep_robj_xuiz_quizzes SET ".
                " name = ".$ilDB->quote($this->getTitle(), "text").
                " WHERE quiz_id = ".$ilDB->quote($this->getId(), "integer")
        );
    }

    /**
     * Delete this quiz object. This will also delete everything in the databse
     * that is related to this quiz: questions, choices, rounds and answers
     */
    function doDelete(){
        global $ilDB;

        // Delete all answers for rounds that correspond to this quiz
        $ilDB->manipulate("DELETE FROM rep_robj_xuiz_answers WHERE ".
                " round_id IN (SELECT DISTINCT round_id FROM rep_robj_xuiz_rounds
			WHERE quiz_id = ".$ilDB->quote($this->getId(), "integer").")"
        );

        // Delete all rounds that correnspond to this quiz
        $ilDB->manipulate("DELETE FROM rep_robj_xuiz_rounds WHERE ".
                " quiz_id = ".$ilDB->quote($this->getId(), "integer")
        );

        // Delete all choices for questions that belong to this quiz
        $ilDB->manipulate("DELETE FROM rep_robj_xuiz_choices WHERE ".
                " question_id IN (SELECT DISTINCT question_id FROM rep_robj_xuiz_qs
			WHERE quiz_id = ".$ilDB->quote($this->getId(), "integer").")"
        );

        // Delete all questions that belong to this quiz
        $ilDB->manipulate("DELETE FROM rep_robj_xuiz_qs WHERE ".
                " quiz_id = ".$ilDB->quote($this->getId(), "integer")
        );

        // Delete this quiz
        $ilDB->manipulate("DELETE FROM rep_robj_xuiz_quizzes WHERE ".
                " quiz_id = ".$ilDB->quote($this->getId(), "integer")
        );
    }

    /**
     * Do Cloning. This is still not implemented.
     *
     */
    public function doCloneObject($infos,$a_copy_id,$from){
        global $ilDB;
        // Get IDs
        $toID = $infos->id."";
        $fromReference = $from;
        $row = $ilDB->fetchAssoc( $ilDB->query("
			  SELECT options
	          FROM copy_wizard_options
	          WHERE source_id = '-5' and copy_id = ".$ilDB->quote($fromReference, "integer") ) );
        $options2 = unserialize($row["options"]);
        $fromIDReference = implode("", $options2);
        $row = $ilDB->fetchAssoc( $ilDB->query("
      			  SELECT obj_id
      	          FROM  object_reference
      	          WHERE ref_id = ".$ilDB->quote($fromIDReference, "integer") ) );
        $fromID = $row["obj_id"];


        // Fragen
        $set = $ilDB->query("
			  SELECT *
	          FROM rep_robj_xuiz_qs
	          WHERE quiz_id = ".$ilDB->quote($fromID, "integer"));
        while ($rec = $ilDB->fetchAssoc($set)){
            $question_id = $ilDB->nextID('rep_robj_xuiz_qs');
            $statement = $ilDB->prepare("INSERT INTO rep_robj_xuiz_qs (question_id, quiz_id, type, text) VALUES (?, ?, ?, ?)",
                array("integer", "integer", "integer", "text")
            );
            $data = array($question_id, $toID, $rec["type"], $rec["text"]);
            $statement->execute($data);

            // Antworten
            $set2 = $ilDB->query("
        			  SELECT *
        	          FROM  rep_robj_xuiz_choices
        	          WHERE question_id = ".$ilDB->quote($rec["question_id"], "integer"));
            while ($rec2 = $ilDB->fetchAssoc($set2)){
                $choice_id = $ilDB->nextID('rep_robj_xuiz_choices');
                $statement2 = $ilDB->prepare("INSERT INTO rep_robj_xuiz_choices (choice_id, question_id, correct_value, text) VALUES (?, ?, ?, ?)",
                    array("integer", "integer", "integer", "text")
                );
                $data2 = array($choice_id, $question_id, $rec2["correct_value"], $rec2["text"]);
                $statement2->execute($data2);
            }
        }

    }

    /**
     * Create Question
     *
     * TODO change from prepared statement, as ILIAS guidlines don't like it.
     *
     * @param	text	question_text
     * @param	int		question_type
     */
    function createQuestion($a_text, $a_type){
        global $ilDB, $ilAccess, $ilUser;

        $question_id = $ilDB->nextID('rep_robj_xuiz_qs');
        $statement = $ilDB->prepare("
        		INSERT INTO rep_robj_xuiz_qs (question_id, quiz_id, type, text, question_order) 
        		VALUES (?, ?, ?, ?, ?)",
            array("integer", "integer", "integer", "text", "integer")
        );
        $data = array($question_id, $this->getId(), $a_type, $a_text,$question_id);
        $statement->execute($data);

        return $question_id;
    }

    /**
     * Delete a question. This will delete a question
     * and all its choices in the database
     *
     * @param	int		question_id
     */
    public function deleteQuestion($question_id){
        global $ilDB, $ilUser;

        // corresponding answers are not deleted here intentionally,
        // they are deleted by removing the quiz though

        // Delete all choices that belong to this question
        $ilDB->manipulate("DELETE FROM rep_robj_xuiz_choices WHERE ".
                " question_id = ".$ilDB->quote($question_id, "integer")
        );

        // Delete question itself
        $ilDB->manipulate("DELETE FROM rep_robj_xuiz_qs WHERE ".
                "question_id = ".$ilDB->quote($question_id, "integer")
        );
    }

    /**
     * Update a question
     *
     * @param	int		question_id
     * @param	string	question_text
     * @param	int		question_type
     */
    public function updateQuestion($question_id, $a_text, $a_type){
        global $ilDB, $ilAccess, $ilUser;

        $ilDB->manipulate("UPDATE rep_robj_xuiz_qs SET text= ".$ilDB->quote($a_text, "text")." WHERE question_id = ".$ilDB->quote($question_id, "integer"));
    }

    /**
     * Get Questions
     *
     * @return	array	questions
     */
    public function getQuestions() {
        global $ilDB;

        $set = $ilDB->query("
		  SELECT *
          FROM rep_robj_xuiz_qs
          WHERE quiz_id = ".$ilDB->quote($this->getId(), "integer")
        		." ORDER BY question_order asc"
        		);

        $question = array();
        $questions = array();

        while ($rec = $ilDB->fetchAssoc($set)){
            $question["question_id"] = $rec["question_id"];
            $question["quiz_id"] = $rec["quiz_id"];
            $question["type"] = $rec["type"];
            $question["text"] = $rec["text"];
            $question["question_order"] = $rec["question_order"];
            

            $questions[] = $question;
        }

        return $questions;
    }
    
    
    public function updateOrder($question_id, $question_order){
    	global $ilDB;
    	$ilDB->manipulate("
    			UPDATE rep_robj_xuiz_qs 
    			SET question_order=".$ilDB->quote($question_order, "integer")." 
    			WHERE question_id =".$ilDB->quote($question_id, "integer"));
    }
    
    /**
     * Get Question
     *
     * @param	int		question_id
        */
    public function switchUp($question_id){
    	$qs = $this->getQuestions();
    	for($i = 0; $i < count($qs); ++$i) {
    		$question1 = $qs[$i];
    		if ($question1['question_id'] == $question_id) {
    			$question_order1 = $question1['question_order'];
    			// wenn das erste frage, nichts tun
    			if ($i != 0){
    				$question2 = $qs[$i-1];
    				$question_order2 = $question2['question_order'];
    				$question_id2 = $question2['question_id'];
    				
    				$this->updateOrder($question_id, $question_order2);
    				$this->updateOrder($question_id2, $question_order1);
    				
    				break;
    				
    				
    			}
    		}
    		
    	} 
    }
    
    
    
    
    /**
     * Get Question
     *
     * @param	int		question_id
     */
    public function switchDown($question_id){
    	$qs = $this->getQuestions();
    	for($i = 0; $i < count($qs); ++$i) {
    		$question1 = $qs[$i];
    		if ($question1['question_id'] == $question_id) {
    			$question_order1 = $question1['question_order'];
    			// wenn das erste frage, nichts tun
    			if ($i != count($qs)-1){
    				$question2 = $qs[$i+1];
    				$question_order2 = $question2['question_order'];
    				$question_id2 = $question2['question_id'];
    	
    				$this->updateOrder($question_id, $question_order2);
    				$this->updateOrder($question_id2, $question_order1);
    	
    				break;
    	
    	
    			}
    		}
    	
    	}
    }
    


    /**
     * Get Question
     *
     * @param	int		question_id
     * @return	array	question
     */
    public function getQuestion($question_id) {
        global $ilDB;

        $set = $ilDB->query("
			  SELECT *
	          FROM rep_robj_xuiz_qs
	          WHERE question_id = ".$ilDB->quote($question_id, "integer"));

        $question = array();

        while ($rec = $ilDB->fetchAssoc($set)){
            $question["question_id"] = $rec["question_id"];
            $question["quiz_id"] = $rec["quiz_id"];
            $question["type"] = $rec["type"];
            $question["text"] = $rec["text"];

            return $question;
        }
    }

    /**
     * Create Choice
     *
     * TODO: change to not use prepared statement
     *
     * @param	int		question_id
     * @param	int		correct_value
     * @param	string	question_text
     */
    function createChoice($a_question_id, $a_correct_value, $a_text){
        global $ilDB, $ilAccess, $ilUser;

        // question of type 3 (numeric) are only allowded to have maximally one choice
        // this choice contains all necessary values (maximum, minimum, step range, correct value)
        $question = $this->getQuestion($a_question_id);
        if($question['type'] == 3 && $this->getChoicesCount($a_question_id) > 0)
            return false;

        // get next id first
        $choice_id = $ilDB->nextID('rep_robj_xuiz_choices');

        $statement = $ilDB->prepare("INSERT INTO rep_robj_xuiz_choices (choice_id, question_id, correct_value, text, choice_order) VALUES (?, ?, ?, ?,?)",
            array("integer", "integer", "integer", "text", "integer")
        );
        $data = array($choice_id, $a_question_id, $a_correct_value, $a_text, $choice_id);

        $statement->execute($data);

        return true;
    }

    /**
     * Update a choice
     *
     * @param	int		choice_id
     * @param	int		correct_value
     * @param	string	choice_text
     * @param	int		choice_order
     */
    public function updateChoice($choice_id, $a_correct_value, $a_text, $choice_order){
        global $ilDB, $ilAccess, $ilUser;

        $ilDB->manipulate("UPDATE rep_robj_xuiz_choices SET text= ".$ilDB->quote($a_text, "text").", correct_value= ".$ilDB->quote($a_correct_value, "text").", choice_order= ".$ilDB->quote($choice_order, "integer")." WHERE choice_id = ".$ilDB->quote($choice_id, "integer"));
    }

    /**
     * Delete a choice
     *
     * @param	int		choice_id
     */
    public function deleteChoice($choice_id){
        global $ilDB, $ilUser;

        // corresponding answers are not deleted here intentionally,
        // they are deleted by removing the quiz though

        $ilDB->manipulate("DELETE FROM rep_robj_xuiz_choices WHERE ".
                "choice_id = ".$ilDB->quote($choice_id, "integer")
        );
    }

    /**
     * Get choices matching a question
     *
     * @param	int			question_id
     * @return	array 		choices
     */
    public function getChoices($question_id) {
        global $ilDB;

        $set = $ilDB->query("
		          SELECT *
		          FROM rep_robj_xuiz_choices
		          WHERE question_id = ".$ilDB->quote($question_id, "integer")
                ." ORDER BY choice_order asc"
        );

        $choice = array();
        $choices = array();

        while ($rec = $ilDB->fetchAssoc($set)){
            $choice["choice_id"] = $rec["choice_id"];
            $choice["question_id"] = $rec["question_id"];
            $choice["correct_value"] = $rec["correct_value"];
            $choice["text"] = $rec["text"];
            $choice["choice_order"] = $rec["choice_order"];
            $choices[] = $choice;
        }
        return $choices;
    }

    /**
     * Get choices count, matching a question
     *
     * @param	int		question_id
     * @return	int 	count
     */
    public function getChoicesCount($question_id) {
        global $ilDB;

        $ilDB->setLimit(1);
        $result = $ilDB->query("
			          SELECT COUNT(*) as cnt
			          FROM rep_robj_xuiz_choices
			          WHERE question_id = ".$ilDB->quote($question_id, "integer")
        );
        $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
        return $row["cnt"];
    }

    /**
     * End current round. This will finish the current round. Therefore an endDate is inserted to the round entry into the db.
     * Also the directory where the QR Code was stored is removed.
     */
    public function endCurrentRound(){
        global $ilDB, $ilAccess, $ilUser;

        $now = new ilDateTime(time(),IL_CAL_UNIX);
        $currentRound = $this->getCurrentRound($this->getId());
        $round_id = $currentRound['round_id'];
        $ilDB->manipulate("UPDATE rep_robj_xuiz_rounds SET end_date= ".$ilDB->quote($now->get(IL_CAL_DATETIME), "timestamp")." WHERE round_id = ".$ilDB->quote($round_id, "integer"));

        // remove temporary directory used to store the qr code image
        ilUtil::delDir(ilUtil::getDataDir()."/MobileQuiz_data/".$round_id);
    }

    /**
     * Begin current round. That will create a new round entry with the current dateTime as start_date.
     * Then temporary directory will be created.
     * Then QR Code image will be created and stored to filesystem into the temporary directory.
     */
    public function beginCurrentRound(){
        global $ilDB, $ilAccess, $ilUser;

        $now = new ilDateTime(time(),IL_CAL_UNIX);
        $round_id = $ilDB->nextID('rep_robj_xuiz_rounds');
        $ilDB->manipulateF("INSERT INTO rep_robj_xuiz_rounds (round_id, quiz_id, start_date) VALUES ".
                " (%s,%s,%s)",
            array("integer", "integer", "timestamp"),
            array($round_id,$this->getId(),$now->get(IL_CAL_DATETIME)));

        // create temporary directory to store the qr code image
        ilUtil::makeDirParents(ilUtil::getDataDir()."/MobileQuiz_data/".$round_id);

        // please change this if you move your frontend installation out of your ILIAS plugin directory:
        $frontend_url = "Customizing/global/plugins/Services/Repository/RepositoryObject/MobileQuiz/frontend/";
        // TODO: Workaround for shorten
        //$frontend_url = "quiz15/";

        // create qr code image
        $quiz_id = $this->getId();
        $currentRound = $this->getCurrentRound($quiz_id);
        $round_id = $currentRound[round_id];
        // get hostname and check if proxy is used
		$hostname = (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : $_SERVER['SERVER_NAME'];
        $url = (!empty($_SERVER['HTTPS'])) ? "https://".$hostname.$_SERVER['REQUEST_URI'] : "http://".$hostname.$_SERVER['REQUEST_URI'];
        // crafting quiz url:
        $tmp = explode('/',$url);
        $dmy = array_pop($tmp);
        $server_url = (implode('/',$tmp) . '/');
        // TODO: Workaround for shorten
        $quiz_url = $server_url.$frontend_url."index.php?quiz_id=".$quiz_id."&round_id=".$round_id;
        //$quiz_url = $server_url.$frontend_url."quiz_id=".$quiz_id."&round_id=".$round_id;

        //short url
        include_once('class.ilObjMobileQuizUrlShorter.php');
        $url_shorter = new ilObjMobileQuizUrlShorter();
        $shorted_url = $url_shorter->shortURL($quiz_url);


        mkdir(ilUtil::getWebspaceDir()."/MobileQuiz_data/".$round_id, 0700, true);
        // uncomment the following line for not shorted urls
        QRcode::png($shorted_url,ilUtil::getWebspaceDir()."/MobileQuiz_data/".$round_id."/qrcode.png", 'L', 15, 2);
        //QRcode::png($quiz_url,ilUtil::getWebspaceDir()."/MobileQuiz_data/".$round_id."/qrcode.png", 'L', 15, 2);

    }

    /**
     * Get current round of a quiz
     * (we can assume that no concurrent rounds are possible:
     * therefore always the one with the highest round_id)
     *
     * @param	int		quiz_id
     * @return	round 	round
     */
    public function getCurrentRound($quiz_id) {
        global $ilDB;

        $ilDB->setLimit(1);
        $set = $ilDB->query("
			  SELECT *
	          FROM rep_robj_xuiz_rounds
	          WHERE quiz_id = ".$ilDB->quote($quiz_id, "integer")." ORDER BY round_id DESC");

        $round = array();

        while ($rec = $ilDB->fetchAssoc($set)){
            $round["round_id"] = $rec["round_id"];
            $round["quiz_id"] = $rec["quiz_id"];
            $round["start_date"] = $rec["start_date"];
            $round["end_date"] = $rec["end_date"];

            return $round;
        }
    }

    /**
     * Get all rounds of a quiz
     *
     * @param	int		quiz_id
     * @return	round 	rounds
     */
    public function getRounds() {
        global $ilDB;

        $set = $ilDB->query("
				  SELECT *
		          FROM rep_robj_xuiz_rounds
		          WHERE quiz_id = ".$ilDB->quote($this->getId(), "integer")." ORDER BY round_id DESC");

        $rounds = array();
        $round = array();

        while ($rec = $ilDB->fetchAssoc($set)){
            $round["round_id"] = $rec["round_id"];
            $round["quiz_id"] = $rec["quiz_id"];
            $round["start_date"] = $rec["start_date"];
            $round["end_date"] = $rec["end_date"];
            $round["type"] = $rec["type"];

            $rounds[] = $round;
        }
        return $rounds;
    }

    /**
     * Get answers matching a round_id
     *
     * @param	int			round_id
     * @return	answers 	answers
     */
    public function getAnswers($round_id) {
        global $ilDB;

        $set = $ilDB->query("
			          SELECT *
			          FROM rep_robj_xuiz_answers
			          WHERE round_id = ".$ilDB->quote($round_id, "integer")
        );

        $answer = array();
        $answers = array();

        while ($rec = $ilDB->fetchAssoc($set)){
            $answer["answer_id"] = $rec["answer_id"];
            $answer["round_id"] = $rec["round_id"];
            $answer["choice_id"] = $rec["choice_id"];
            $answer["value"] = $rec["value"];
            $answer["user_string"] = $rec["user_string"];

            $answers[] = $answer;
        }
        return $answers;
    }

    /**
     * Get all answers user_strings corresponding to a quiz round.
     *
     * @param	int			round_id
     * @return	answers 	answers user_sting
     */
    public function getDistinctAnswers($round_id) {
        global $ilDB;

        $set = $ilDB->query("
				          SELECT DISTINCT user_string
				          FROM rep_robj_xuiz_answers
				          WHERE round_id = ".$ilDB->quote($round_id, "integer")
        );

        $answer = array();
        $answers = array();

        while ($rec = $ilDB->fetchAssoc($set)){
            $answer["user_string"] = $rec["user_string"];

            $answers[] = $answer;
        }
        return $answers;
    }

    /**
     * Get choices together with their answers correlating to a certain question and a certain answer
     *
     * @param	int			question_id
     * @param	string		user_string
     * @return	choices 	choices
     */
    public function getChoicesOfQuestionAnswer($question_id, $user_string) {
        global $ilDB;

        $set = $ilDB->query("
				          SELECT *
				          FROM rep_robj_xuiz_choices AS c, rep_robj_xuiz_answers AS a
				          WHERE c.question_id = ".$ilDB->quote($question_id, "integer")."
				          AND c.choice_id = a.choice_id
				          AND a.user_string = ".$ilDB->quote($user_string, "text")
        );
        $answer = array();
        $answers = array();

        while ($rec = $ilDB->fetchAssoc($set)){
            $answer["correct_value"] = $rec["correct_value"];
            $answer["value"] = $rec["value"];
            $answers[] = $answer;
        }
        return $answers;
    }


    public function createChartForNumeric($filename, $data) {
        /* Create and populate the pData object */
        $MyData = new pData();
        $MyData->addPoints(array(60,30,10),"Answers");
        $MyData->setAxisName(0,"Antworten (%)");
        $MyData->addPoints(array("I do agree  ","I disagree  ","No opinion  "),"Options");
        $MyData->setAbscissa("Options");

        /* Create the pChart object */
        $myPicture = new pImage(700,420,$MyData);

        /* Define the default font */
        $myPicture->setFontProperties(array("FontName"=>"./Customizing/global/plugins/Services/Repository/RepositoryObject/MobileQuiz/lib/pChart/fonts/verdana.ttf","FontSize"=>12));

        /* Set the graph area */
        $myPicture->setGraphArea(70,60,480,200);
        $myPicture->drawGradientArea(70,60,480,200,DIRECTION_HORIZONTAL,array("StartR"=>200,"StartG"=>200,"StartB"=>200,"EndR"=>240,"EndG"=>240,"EndB"=>240,"Alpha"=>30));

        /* Draw the chart scale */
        $scaleSettings = array("DrawXLines"=>FALSE,"Mode"=>SCALE_MODE_START0,"GridR"=>0,"GridG"=>0,"GridB"=>0,"GridAlpha"=>10,"Pos"=>SCALE_POS_TOPBOTTOM);
        $myPicture->drawScale($scaleSettings);

        /* Turn on shadow computing */
        $myPicture->setShadow(TRUE,array("X"=>1,"Y"=>1,"R"=>0,"G"=>0,"B"=>0,"Alpha"=>10));

        /* Draw the chart */
        $myPicture->drawBarChart(array("Rounded"=>TRUE,"Surrounding"=>30));


        /* Render the picture (choose the best way) */
        $myPicture->Render($filename);

    }

    /**
     * Delete a round
     *
     * @param	int	round_id
     */
    public function deleteRound($round_id) {
        global $ilDB, $ilUser;

        // the corresponding database entry in rep_robj_xuiz_rounds is deleted
        $ilDB->manipulate("DELETE FROM rep_robj_xuiz_answers WHERE ".
                " round_id = ".$ilDB->quote($round_id, "integer")
        );
        // the round's answers are deleted
        $ilDB->manipulate("DELETE FROM rep_robj_xuiz_rounds WHERE ".
                " round_id = ".$ilDB->quote($round_id, "integer")
        );
    }

    /**
     * Change to status of a round to active, inactive or passive
     *
     * @param	int	round_id
     */
    public function changeRoundStatus($round_id) {
        global $ilDB, $ilUser;

        $ilDB->setLimit(1);
        $set = $ilDB->query("
            			  SELECT *
            	          FROM rep_robj_xuiz_rounds
            	          WHERE round_id = ".$ilDB->quote($round_id, "integer")." ORDER BY round_id DESC");

        while ($rec = $ilDB->fetchAssoc($set)){

            if ( empty( $_GET['activate'] ) && empty( $_GET['deactivate'] ) ) {
                $ilDB->manipulate("UPDATE rep_robj_xuiz_rounds SET type = 'passive' WHERE ".
                        " round_id = ".$ilDB->quote($round_id, "integer")
                );
                $ilDB->manipulate("UPDATE rep_robj_xuiz_rounds SET end_date = NULL WHERE ".
                        " round_id = ".$ilDB->quote($round_id, "integer")
                );
            } else {
                $ilDB->manipulate("UPDATE rep_robj_xuiz_rounds SET type = 'normal' WHERE ".
                        " round_id = ".$ilDB->quote($round_id, "integer")
                );
                if ( !empty( $rec["end_date"] ) || !empty( $_GET['activate'] )) {
                    $ilDB->manipulate("UPDATE rep_robj_xuiz_rounds SET end_date = NULL WHERE ".
                            " round_id = ".$ilDB->quote($round_id, "integer")
                    );
                } else {
                    $ilDB->manipulate("UPDATE rep_robj_xuiz_rounds SET end_date = NOW() WHERE ".
                            " round_id = ".$ilDB->quote($round_id, "integer")
                    );
                }

            }
        }
    }

}

?>
