<?php 

class mark_calculator
{
    public static function recalcluate_marks($itemid=0,$gquizid=0)
    {
        global $DB ; 
        if($gquizid===0){
            $gquizid = $DB->get_record('gquiz_item',['id'=>$itemid])->gquiz;
        }
        
        $submitted = $DB->get_records('gquiz_completed',['gquiz'=>$gquizid]);
        foreach ($submitted as $completed) {
            self::calculate_mark($completed);
        }
    }
    public static function calculate_mark($completed)
    {
        global $DB;
        $grade=0;
        $mark=0;
        
        $items= $DB->get_records('gquiz_item',['gquiz'=>$completed->gquiz,'isgraded'=>1]);
        
        foreach ($items as $item) {
                $gradeditem = $DB->get_record('gquiz_graded_qustions',['itemid'=>$item->id]);
                $grade+= $gradeditem->grade;
                $answer = $DB->get_record('gquiz_value',['item'=>$item->id,'completed'=>$completed->id]);
                if($answer){
                    if($gradeditem->answer == $answer->value){
                        $mark+=$gradeditem->grade;
                    }
                }    
        }
        $data = new stdClass;
        $data->id = $completed->id;        
        $data->mark =($mark/$grade*1.0)*100;
        if(is_nan($data->mark)) $data->mark = 0;
        $DB->update_record('gquiz_completed',$data);
    }
}