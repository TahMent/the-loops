jQuery(function(a){var b=a("#loop-min-date, #loop-max-date").datepicker({changeMonth:!0,onSelect:function(c){var d=this.id=="loop-min-date"?"minDate":"maxDate",e=a(this).data("datepicker"),f=a.datepicker.parseDate(e.settings.dateFormat||a.datepicker._defaults.dateFormat,c,e.settings);b.not(this).datepicker("option",d,f)}}),c=function(){a(this).tagsInput({height:"5em",width:"24em",defaultText:"add a value",delimiter:"\t"})};a(".tl-parameter").not(".hide-if-js").find(".tl-tagsinput").each(c),a(".tl-add-parameter").click(function(b){b.preventDefault();var d,e,f;d=a(this).parent(),e=d.siblings().length-1,f=d.next().clone().removeClass("hide-if-js").wrap("<div>").parent().html().replace(/{key}/gi,e),f=a(f),f.insertBefore(a(this)),f.find(".tl-tagsinput").each(c)}),a(".inside").on("click",".tl-delete",function(b){b.preventDefault(),a(this).parents(".tl-parameter").remove()});var d=function(b,c,d,e){var f=!1,g=b.val(),h=a.isArray(g);h?a.each(g,function(b,d){if(a.inArray(d,c)>-1){f=!0;return}}):c?f=a.inArray(g,c)>-1:(g=a.trim(g),f=g.length>0),d=d?d.join(","):null,e=e?e.join(","):null,f?(a(d).show("slow"),a(e).hide("slow")):(a(d).hide("slow"),a(e).show("slow"))};a("#loop_orderby").change(function(){d(a(this),["meta_value","meta_value_num"],[".tl_meta_key"])}),a("#loop_pagination").change(function(){d(a(this),["none"],[".tl_offset",".tl_paged"])}),a("#loop_post_status").change(function(){d(a(this),["private"],[".tl_readable"])}),a("#loop_post_type").change(function(){d(a(this),["attachment"],[".tl_post_mime_type"])}),a("#loop_s").change(function(){d(a(this),null,[".tl_exact",".tl_sentence"])}),a("#loop_date_type").change(function(){a(this).val()==="dynamic"?d(a(this),["dynamic"],[".tl_days"],[".tl_date",".tl_period"]):a(this).val()==="period"?d(a(this),["period"],[".tl_period"],[".tl_date",".tl_days"]):a(this).val()==="static"&&d(a(this),["static"],[".tl_date"],[".tl_days",".tl_period"])})});