<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'gquiz', language 'ar', branch 'MOODLE_20_STABLE'
 *
 * @package mod_gquiz
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['add_item'] = 'أضف سؤال للنشاط';
$string['add_items'] = 'أضف سؤال للنشاط';
$string['add_pagebreak'] = 'إضافة فاصل صفحات';
$string['adjustment'] = 'تعديلات';
$string['after_submit'] = 'بعد التسليم';
$string['allowfullanonymous'] = 'إسمح بإخفاء الهوية الكامل';
$string['analysis'] = 'تحليل';
$string['anonymous'] = 'غير مشخصن';
$string['anonymous_edit'] = 'قم بتسجيل أسماء المستخدمين';
$string['anonymous_entries'] = 'مدخلات مجهولة الهوية';
$string['anonymous_user'] = 'مستخدم غير مشخصن';
$string['append_new_items'] = 'إلحاق عناصر جديدة';
$string['autonumbering'] = 'ترقيم آلي';
$string['autonumbering_help'] = 'تمكين أو تعطيل الترقيم التلقائي لكل سؤال';
$string['average'] = 'متوسط';
$string['bold'] = 'داكن';
$string['cancel_moving'] = 'الغي النقل';
$string['cannotsavetempl'] = 'حفظ القوالب غير مسموح به';
$string['captcha'] = 'كلمة التحقق';
$string['captchanotset'] = 'لم يتم إعداد كلمة التحقق.';
$string['check'] = 'متعدد الخيارات - متعدد الاجابات';
$string['check_values'] = 'الاجابات المحتمله';
$string['checkbox'] = 'متعدد الخيارات - يسمح بإجابات متعددة (خانات تأشير)';
$string['choosefile'] = 'إختر الملف';
$string['chosen_feedback_response'] = 'استجابة الإفادة المختارة';
$string['complete_the_form'] = 'اجب على الأسئلة.....';
$string['completed'] = 'تم';
$string['completed_feedbacks'] = 'الاجابات المسلمة';
$string['completionsubmit'] = 'المعاينة بمثابة مكتمل إذا تم تسليم الإفادة';
$string['configallowfullanonymous'] = 'عند ضبطه على \'نعم\'، يمكن للمستخدمين إكمال نشاط الإفادة من الصفحة الرئيسية دون الحاجة إلى تسجيل دخولهم.';
$string['confirmdeleteentry'] = 'هل أنت متأكد من رغبتك في حذف هذا الادخال؟';
$string['confirmdeleteitem'] = 'هل أنت متأكد من رغبتك في حذف هذا العنصر؟';
$string['confirmdeletetemplate'] = 'هل أنت متأكد من رغبتك في حذف هذا القالب؟';
$string['confirmusetemplate'] = 'هل أنت متأكد من رغبتك في استعمال هذا القالب؟';
$string['continue_the_form'] = 'استمر من';
$string['count_of_nums'] = 'عدد الأرقام';
$string['courseid'] = 'معرف المقرر الدراسي';
$string['creating_templates'] = 'حفظ هذه الأسئلة كقالب جديد';
$string['delete_entry'] = 'احذف المدخل';
$string['delete_item'] = 'احذف سؤال';
$string['delete_old_items'] = 'احذف العناصر القديمة';
$string['delete_template'] = 'حذف القالب';
$string['delete_templates'] = 'حذف القالب...';
$string['depending'] = 'التبِعات';
$string['depending_help'] = 'من الممكن إظهار العنصر المعتمد على قيمة عنصر آخر.<br />
<strong>إليك مثالاً على ذلك.</strong><br />
<ul>
<li>أولاً، قم بإنشاء عنصر ليكون هناك عنصر آخر معتمد عليه.</li>
<li>بعدها، أضف فاصل صفحات.</li>
<li>ثم أضف العناصر المعتمدة على قيمة العنصر الذي أنشأته آنفاً. إختر العنصر من القائمة المسماة "العنصر المعتمد عليه" وأكتب القيمة المطلوبة في مربع النص المسمى "القيمة المعتمد عليها".</li>
</ul>
<strong>هيكل العنصر ينبغي أن يبدو بهذا الشكل.</strong>
<ol>
<li>عنصر سؤال: هل لديك سيارة؟ ج: نعم/لا</li>
<li>فاصل صفحات</li>
<li>عنصر سؤال: ما لون سيارتك؟<br />
(هذا العنصر يعتمد على العنصر 1 مع القيمة = نعم)</li>
<li>عنصر سؤال: لماذا لا تمتلك سيارة؟<br />
(هذا العنصر يعتمد على العنصر 1 مع القيمة = لا)</li>
<li> ... عناصر أخرى</li>
</ol>';
$string['dependitem'] = 'العنصر المعتَمَد عليه';
$string['dependvalue'] = 'القيمة المعتَمَد عليها';
$string['description'] = 'الوصف';
$string['do_not_analyse_empty_submits'] = 'لا تقم بتحليل التسليمات الفارغة';
$string['drop_feedback'] = 'أحذف من المقرر الدراسي هذا';
$string['dropdown'] = 'خيارات متعددة - إجابة واحدة المستدلة المسموح بها';
$string['dropdown_values'] = 'إجابات';
$string['dropdownlist'] = 'خيارات متعددة - إجابة واحدة المستدلة';
$string['dropdownrated'] = 'المستدلة';
$string['edit_item'] = 'تحرير السؤال';
$string['edit_items'] = 'تحرير الأسئلة';
$string['email_notification'] = 'ارسال إشعارات بريد الإلكتروني';
$string['emailnotification'] = 'إشعارات بريد الإلكتروني';
$string['emailnotification_help'] = '';
$string['emailteachermail'] = 'أتم {$a->username} الإفادة: \'{$a->feedback}\' يمكنك مشاهدتها هنا: {$a->url}';
$string['emailteachermailhtml'] = 'أتم {$a->username} الإفادة: <i>\'{$a->feedback}\'</i> يمكنك مشاهدتها <a href="{$a->url}">هنا</a>.';
$string['entries_saved'] = 'شكراً، لقد تم حفظ إجاباتك';
$string['export_questions'] = 'تصدير الأسئلة';
$string['export_to_excel'] = 'تصدير إلى Excel';
$string['feedback:complete'] = 'إكمال إفادة';
$string['feedback:createprivatetemplate'] = 'إنشاء قالب خاص';
$string['feedback:createpublictemplate'] = 'إنشاء قالب عام';
$string['feedback:deletesubmissions'] = 'حذف التسليمات المكتملة';
$string['feedback:deletetemplate'] = 'حذف القالب';
$string['feedback:edititems'] = 'تحرير العناصر';
$string['feedback:mapcourse'] = 'تعيين المقررات الدراسية للإفادات العمومية';
$string['feedback:receivemail'] = 'تلقي إشعار البريد الالكتروني';
$string['feedback:view'] = 'معاينة الإفادة';
$string['feedback:viewanalysepage'] = 'شاهد صفحة التحليل بعد الإرسال';
$string['feedback:viewreports'] = 'معاينة التقارير';
$string['feedback_is_not_for_anonymous'] = 'الإفادة ليست لمجهولي الهوية';
$string['feedback_is_not_open'] = 'الإفادة ليست مفتوحة';
$string['feedback_options'] = 'خيارات الإفادة';
$string['feedbackclose'] = 'إغلاق الأفادة';
$string['feedbackopen'] = 'فتح الإفادة';
$string['file'] = 'الملف';
$string['filter_by_course'] = 'الترشيح حسب المقرر الدراسي';
$string['handling_error'] = 'وقع خطأ عند معاملة إجراء وحدة الإفادة';
$string['hide_no_select_option'] = 'إخفِ خيار "غير محدد"';
$string['horizontal'] = 'أفقي';
$string['import_questions'] = 'استيراد الأسئلة';
$string['import_successfully'] = 'تم الاستيراد بنجاح';
$string['importfromthisfile'] = 'استورد من هذا الملف';
$string['info'] = 'المعلومات';
$string['infotype'] = 'نوع المعلومات';
$string['insufficient_responses'] = 'إجابات غير كافية';
$string['insufficient_responses_for_this_group'] = 'لا توجد إجابات كافية لهذه المجموعة';
$string['insufficient_responses_help'] = 'لتكون الإفادة غير مشخصنة، لا بد من وجود إجابتين على الأقل.';
$string['item_label'] = 'الملصق';
$string['item_name'] = 'سؤال';
$string['items_are_required'] = 'يجب الإجابة على الأسئلة ذات النجمة.';
$string['label'] = 'الملصق';
$string['line_values'] = 'تقييم';
$string['mapcourse'] = 'قم بتعيين الإفادة إلى المقررات الدراسية';
$string['mapcourse_help'] = 'إفتراضياً، نماذج الإفادات المنشأة في صفحتك الرئيسية تكون متاحة على مستوى عموم الموقع وستظهر في كل المقررات الدراسية باستعمال كتلة الإفادة. يمكنك إجبار نموذج الإفادة على الظهور عبر جعله كتلة دبقة أو تقنين المقررات الدراسية التي يظهر فيها عبر تعيينه إلى مقررات محددة.';
$string['mapcourseinfo'] = 'هذه إفادة على مستوى عموم الموقع وهي متاحة في كل المقررات الدراسية باستعمال كتلة الإفادة. مع ذلك، يمكنك تحديد المقررات الدراسية التي ستظهر فيها عبر تعيينها. إبحث عن المقرر المطلوب وقم بتعيينه إلى هذه الإفادة.';
$string['mapcoursenone'] = 'لم يتم تعيين مقررات. الإفادة متاحة لجميع المقررات الدراسية.';
$string['mapcourses'] = 'قم بتعيين الإفادة إلى المقررات الدراسية';
$string['mappedcourses'] = 'المقررات الدراسية المعينة';
$string['maximal'] = 'الأقصى';
$string['messageprovider:message'] = 'تذكير إفادة';
$string['messageprovider:submission'] = 'إعلامات الإفادة';
$string['mode'] = 'نمط';
$string['modulename'] = 'اختبار عام';
$string['modulename_help'] = 'وحدة نشاط الإفادة تُمكن المعلم من إنشاء اختبار مخصص لجمع آراء المشاركين باستعمال أنواع متعددة من الأسئلة بضمنها: متعدد الخيارات، نعم/لا، والإدخال النصي.

يمكن إخفاء هويات أصحاب الإجابات حسب الرغبة، ويمكن إظهار النتائج لكل المشاركين أو تقييدها بالمعلمين فقط. يمكن أيضاً إكمال اي نشاطات من الإفادة في الصفحة الأمامية للموقع من قبل المستخدمين دون الحاجة إلى تسجيل دخولهم.

يمكن استعمال نشاطات الإفادة:

* لتقييم المساق، للمساعدة في تحسين المحتوى للمشاركين المستقبليين
* لتمكين المشاركين من التسجيل في وحدات المساق، أحداثه... إلخ.
* استطلاع آراء الضيوف بشأن إختيار المساقات، سياسات المدرسة... إلخ.
* الاختبارات بشأن محاربة الاعتداءات الشخصية حيث يمكن للطلاب الإبلاغ عن الحوادث دون كشف هوياتهم';
$string['modulenameplural'] = 'اختبار عام';
$string['move_here'] = 'أنقل هناء';
$string['move_item'] = 'أنقل هذا السؤال ';
$string['movedown_item'] = 'أنقل هذا السؤال إلى الأسفل';
$string['moveup_item'] = 'أنقل هذا السؤال إلى الأعلى';
$string['multichoice'] = 'متعدد الخيارات';
$string['multichoice_values'] = 'قيم متعدد الخيارات';
$string['multichoicerated'] = 'متعدد الخيارات (مقيَّم)';
$string['multichoicetype'] = 'نوع متعدد الخيارات';
$string['multiple_submit'] = 'إرسالات متعددة';
$string['multiplesubmit'] = 'إرسالات متعددة';
$string['multiplesubmit_help'] = 'عند تمكينه للاستطلاعات غير المشخصنة، يمكن للمستخدمين تقديم إفاداتهم لعدد غير محدد من المرات.';
$string['name'] = 'اسم';
$string['name_required'] = 'الاسم مطلوب';
$string['next_page'] = 'الصفحة التالية';
$string['no_handler'] = 'لا يتوفر معامل إجراء لـ';
$string['no_itemlabel'] = 'بلا ملصق';
$string['no_itemname'] = 'لا يوجد اسم عنصر';
$string['no_items_available_yet'] = 'لم يتم وضع أي أسئلة بعد';
$string['no_templates_available_yet'] = 'لا قوالب متاحة بعد';
$string['non_anonymous'] = 'سيتم تسجيل اسم المستخدم وعرضه مع الإجابات';
$string['non_anonymous_entries'] = 'الإدخالات مكشوفة الهوية ({$a})';
$string['non_respondents_students'] = 'غير مستجيبين';
$string['not_completed_yet'] = 'لم يكتمل بعد';
$string['not_selected'] = 'غير محددة';
$string['not_started'] = 'لم يتم البدء';
$string['numeric'] = 'إجابة رقمية';
$string['numeric_range_from'] = 'المجال من:';
$string['numeric_range_to'] = 'المجال إلى:';
$string['of'] = 'من';
$string['oldvaluespreserved'] = 'سيتم الاحتفاظ بكل الأسئلة القديمة والقيم المعينة لها';
$string['oldvalueswillbedeleted'] = 'سيتم حذف كل الأسئلة الحالية وإجاباتها.';
$string['only_one_captcha_allowed'] = 'يُسمح بكلمة تحقق واحدة فقط في الإفادة';
$string['overview'] = 'نظرة عامة';
$string['page'] = 'صفحة';
$string['page-mod-feedback-x'] = 'أي صفحة لوحدة نشاط الإفادة';
$string['page_after_submit'] = 'الصفحة بعد الإرسال';
$string['pagebreak'] = 'فاصل الصفحات';
$string['picture'] = 'صورة';
$string['picture_file_list'] = 'قائمة صور';
$string['pluginadministration'] = 'إدارة الإفادة';
$string['pluginname'] = 'إفادة';
$string['position'] = 'موضع';
$string['preview'] = 'معاينة';
$string['previous_page'] = 'الصفحة السابقة';
$string['public'] = 'عام';
$string['question'] = 'السؤال';
$string['questions'] = 'الأسئلة';
$string['radio'] = 'متعدد الاختيارات - إجابة واحدة';
$string['radio_values'] = 'الإجابات';
$string['ready_feedbacks'] = 'الإفادات الجاهزة';
$string['required'] = 'مطلوب';
$string['resetting_data'] = 'أعد تعيين إجابات الإفادة';
$string['resetting_feedbacks'] = 'تجري إعادة تعيين الإفادات';
$string['response_nr'] = 'رقم الإجابة';
$string['responses'] = 'إجابات';
$string['responsetime'] = 'وقت الإجابة';
$string['save_as_new_item'] = 'أحفظ كسؤال جديد';
$string['save_as_new_template'] = 'الحفظ بمثابة قالب جديد';
$string['save_entries'] = 'تسليم إجاباتك';
$string['save_item'] = 'أحفظ السؤال';
$string['saving_failed'] = 'فشل الحفظ';
$string['search_course'] = 'البحث في مقرر دراسي';
$string['searchcourses'] = 'البحث في المقررات الدراسية';
$string['searchcourses_help'] = 'إبحث عن رمز أو اسم المقرر/المقررات التي تريد ربطها مع هذه الإفادة.';
$string['selected_dump'] = 'الفهارس المختارة لمتغير $SESSION مطروحة أدناه:';
$string['send'] = 'إرسال';
$string['send_message'] = 'ارسل رسالة';
$string['separator_decimal'] = '.';
$string['separator_thousand'] = '،';
$string['show_all'] = 'إظهار الكل';
$string['show_analysepage_after_submit'] = 'عرض صفحة التحليل بعد الإرسال';
$string['show_entries'] = 'عرض الردود';
$string['show_entry'] = 'إظهار إجابة';
$string['show_nonrespondents'] = 'معاينة غير المستجيبين';
$string['site_after_submit'] = 'الموقع بعد التسليم';
$string['sort_by_course'] = 'أفرز حسب المقرر الدراسي';
$string['start'] = 'إبداء';
$string['started'] = 'بداء';
$string['stop'] = 'انتهى';
$string['subject'] = 'موضوع';
$string['switch_item_to_not_required'] = 'تغيير إلى: الجواب غير مطلوب';
$string['switch_item_to_required'] = 'تغيير إلى: الجواب مطلوب';
$string['template'] = 'القالب';
$string['template_saved'] = 'القالب الذي تم حفظه';
$string['templates'] = 'القوالب';
$string['textarea'] = 'إجابة بنص أطول';
$string['textarea_height'] = 'عدد الأسطر';
$string['textarea_width'] = 'العرض';
$string['textfield'] = 'إجابة بنص قصير';
$string['textfield_maxlength'] = 'أقصى عدد مقبول من الحروف';
$string['textfield_size'] = 'عرض حقل النص';
$string['there_are_no_settings_for_recaptcha'] = 'ليست هناك إعدادات لكلمة التحقق';
$string['this_feedback_is_already_submitted'] = 'لقد قمت مسبقاً بإكمال هذا النشاط.';
$string['timeclose'] = 'وقت الإغلاق';
$string['timeopen'] = 'وقت الفتح';
$string['typemissing'] = 'قيمة مفقودة "النوع"';
$string['update_item'] = 'حفظ تغييرات السؤال';
$string['url_for_continue'] = 'إربطه مع النشاط الآتي';
$string['url_for_continue_button'] = 'الوصلة لزر الاستمرار';
$string['url_for_continue_help'] = 'بعد تقديم الإفادة، سيتم عرض زر الاستمرار، والذي يذهب إلى صفحة المساق. كبديل عن ذلك، قد يرتبط بالنشاط الآتي إذا تم وضع رابطه هنا.';
$string['use_one_line_for_each_value'] = 'استعمل سطراً واحداً لكل إجابة!';
$string['use_this_template'] = 'استعمل هذا القالب';
$string['using_templates'] = 'استعمل القالب';
$string['vertical'] = 'عاموي';
 
//Added By gquiz
$string['isgraded']='هل يملك السؤال علامة ؟';
$string['grade']='العلامة';
$string['answer']='الجواب';
