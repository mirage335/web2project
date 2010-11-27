/* $Id$ $URL$ */
var hourMSecs=3600*1000;function isInArray(myArray,intValue){for(var i=0,i_cmp=myArray.length;i<i_cmp;i++){if(myArray[i]==intValue){return true}}return false}function selected(cal,date){cal.sel.value=(cal.date.print("%Y%m%d%H%M"));setDate(cal.form,cal.sel.name);if(cal.dateClicked&&(cal.sel.id=="ini_date"||cal.sel.id=="end_date"))cal.callCloseHandler()}function closeHandler(cal){cal.hide();_dynarch_popupCalendar=null}function showCalendar(id,format,form_name,showsTime,showsOtherMonths){var el=document.getElementById(id);if(_dynarch_popupCalendar!=null){_dynarch_popupCalendar.hide()}else{var cal=new Calendar(1,null,selected,closeHandler);if(typeof showsTime=="string"){cal.showsTime=true;cal.time24=(showsTime=="24")}if(showsOtherMonths){cal.showsOtherMonths=true}_dynarch_popupCalendar=cal;cal.setRange(1900,2070);cal.create()}_dynarch_popupCalendar.setDateFormat(format);_dynarch_popupCalendar.parseDate(el.value);_dynarch_popupCalendar.sel=el;_dynarch_popupCalendar.form=form_name;_dynarch_popupCalendar.showAtElement(el,"Bl");return false}var MONTH_NAMES=new Array('January','February','March','April','May','June','July','August','September','October','November','December','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec');var DAY_NAMES=new Array('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sun','Mon','Tue','Wed','Thu','Fri','Sat');function LZ(x){return(x<0||x>9?"":"0")+x}function formatDate(date,format){format=format+"";var result="";var i_format=0;var c="";var token="";var y=date.getYear()+"";var M=date.getMonth()+1;var d=date.getDate();var E=date.getDay();var H=date.getHours();var m=date.getMinutes();var s=date.getSeconds();var Y,yyyy,yy,MMM,MM,dd,hh,h,mm,ss,ampm,HH,H,KK,K,kk,k;var value=new Object();if(y.length<4){y=""+(y-0+1900)}value["y"]=""+y;value["yyyy"]=y;value["Y"]=y;value["yy"]=y.substring(2,4);value["M"]=M;value["MM"]=LZ(M);value["MMM"]=MONTH_NAMES[M-1];value["NNN"]=MONTH_NAMES[M+11];value["b"]=MONTH_NAMES[M+11];value["d"]=d;value["dd"]=LZ(d);value["E"]=DAY_NAMES[E+7];value["EE"]=DAY_NAMES[E];value["H"]=H;value["HH"]=LZ(H);if(H==0){value["h"]=12}else if(H>12){value["h"]=H-12}else{value["h"]=H}value["hh"]=LZ(value["h"]);if(H>11){value["K"]=H-12}else{value["K"]=H}value["k"]=H+1;value["KK"]=LZ(value["K"]);value["kk"]=LZ(value["k"]);if(H>11){value["a"]="pm"}else{value["a"]="am"}value["m"]=m;value["mm"]=LZ(m);value["s"]=s;value["ss"]=LZ(s);while(i_format<format.length){c=format.charAt(i_format);token="";while((format.charAt(i_format)==c)&&(i_format<format.length)){token+=format.charAt(i_format++)}if(value[token]!=null){result=result+value[token]}else{result=result+token}}return result}function _isInteger(val){var digits="1234567890";for(var i=0,i_cmp=val.length;i<i_cmp;i++){if(digits.indexOf(val.charAt(i))==-1){return false}}return true}function _getInt(str,i,minlength,maxlength){for(var x=maxlength;x>=minlength;x--){var token=str.substring(i,i+x);if(token.length<minlength){return null}if(_isInteger(token)){return token}}return null}function getDateFromFormat(val,format){val=val+"";format=format+"";var i_val=0;var i_format=0;var c="";var token="";var token2="";var x,y;var now=new Date();var year=now.getYear();var month=now.getMonth()+1;var date=1;var hh=now.getHours();var mm=now.getMinutes();var ss=now.getSeconds();var ampm="";while(i_format<format.length){c=format.charAt(i_format);token="";while((format.charAt(i_format)==c)&&(i_format<format.length)){token+=format.charAt(i_format++)}if(token=="yyyy"||token=="yy"||token=="y"||token=="Y"){if(token=="Y"){x=4;y=4}if(token=="yyyy"){x=4;y=4}if(token=="yy"){x=2;y=2}if(token=="y"){x=2;y=4}year=_getInt(val,i_val,x,y);if(year==null){return 0}i_val+=year.length;if(year.length==2){if(year>70){year=1900+(year-0)}else{year=2000+(year-0)}}}else if(token=="MMM"||token=="NNN"){month=0;for(var i=0,i_cmp=MONTH_NAMES.length;i<i_cmp;i++){var month_name=MONTH_NAMES[i];if(val.substring(i_val,i_val+month_name.length).toLowerCase()==month_name.toLowerCase()){if(token=="MMM"||(token=="NNN"&&i>11)){month=i+1;if(month>12){month-=12}i_val+=month_name.length;break}}}if((month<1)||(month>12)){return 0}}else if(token=="EE"||token=="E"){for(var i=0,i_cmp=DAY_NAMES.length;i<i_cmp;i++){var day_name=DAY_NAMES[i];if(val.substring(i_val,i_val+day_name.length).toLowerCase()==day_name.toLowerCase()){i_val+=day_name.length;break}}}else if(token=="MM"||token=="M"){month=_getInt(val,i_val,token.length,2);if(month==null||(month<1)||(month>12)){return 0}i_val+=month.length}else if(token=="dd"||token=="d"){date=_getInt(val,i_val,token.length,2);if(date==null||(date<1)||(date>31)){return 0}i_val+=date.length}else if(token=="hh"||token=="h"){hh=_getInt(val,i_val,token.length,2);if(hh==null||(hh<1)||(hh>12)){return 0}i_val+=hh.length}else if(token=="HH"||token=="H"){hh=_getInt(val,i_val,token.length,2);if(hh==null||(hh<0)||(hh>23)){return 0}i_val+=hh.length}else if(token=="KK"||token=="K"){hh=_getInt(val,i_val,token.length,2);if(hh==null||(hh<0)||(hh>11)){return 0}i_val+=hh.length}else if(token=="kk"||token=="k"){hh=_getInt(val,i_val,token.length,2);if(hh==null||(hh<1)||(hh>24)){return 0}i_val+=hh.length;hh--}else if(token=="mm"||token=="m"){mm=_getInt(val,i_val,token.length,2);if(mm==null||(mm<0)||(mm>59)){return 0}i_val+=mm.length}else if(token=="ss"||token=="s"){ss=_getInt(val,i_val,token.length,2);if(ss==null||(ss<0)||(ss>59)){return 0}i_val+=ss.length}else if(token=="a"){if(val.substring(i_val,i_val+2).toLowerCase()=="am"){ampm="am"}else if(val.substring(i_val,i_val+2).toLowerCase()=="pm"){ampm="pm"}else{return 0}i_val+=2}else{if(val.substring(i_val,i_val+token.length)!=token){return 0}else{i_val+=token.length}}}if(i_val!=val.length){return 0}if(month==2){if(((year%4==0)&&(year%100!=0))||(year%400==0)){if(date>29){return 0}}else{if(date>28){return 0}}}if((month==4)||(month==6)||(month==9)||(month==11)){if(date>30){return 0}}if(hh<12&&ampm=="pm"){hh=hh-0+12}else if(hh>11&&ampm=="am"){hh-=12}var newdate=new Date(year,month-1,date,hh,mm,ss);return newdate.getTime()}