import{y as k,f as v,c as x,o as b,h as C,a as r,p as U,l as Q,J as Z,P as tt,F as $,j as P,t as p,b as L}from"./app-LRmgTAnk.js";import{_ as et}from"./_plugin-vue_export-helper-DlAUqK2U.js";const nt={class:"space-y-4"},rt={key:0,class:"flex flex-col md:flex-row gap-4 justify-between items-start md:items-center bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 print:hidden"},ot={class:"flex items-center gap-3"},it={class:"flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap"},st={class:"flex items-center gap-2"},at=["value"],lt={style:{"margin-top":"10px","border-bottom":"2px solid #111827",position:"relative"}},dt={key:0,style:{position:"absolute",top:"0",right:"0",display:"flex","padding-right":"10px","justify-content":"center","align-items":"center","margin-bottom":"4px"}},ct=["src"],pt={style:{"text-align":"center","font-size":"6pt",color:"#4b5563",margin:"0",padding:"0"}},ut={style:{"text-align":"center","font-size":"6pt",color:"#4b5563","margin-top":"8pt"}},mt={style:{"text-align":"center","font-size":"12pt","font-weight":"700",color:"#111827","margin-top":"5px"}},gt={style:{display:"grid","grid-template-columns":"1fr 1fr","column-gap":"16px","row-gap":"10px","margin-bottom":"16px","font-size":"14px"}},ft={style:{"line-height":"8pt","grid-column":"span 2",display:"flex","align-items":"center","margin-top":"10px"}},ht={style:{"font-weight":"600","text-align":"center",color:"#111827",width:"100%","font-size":"8pt","border-bottom":"1px solid #111827"}},xt={style:{overflow:"visible","margin-bottom":"20px"}},bt={style:{width:"100%","border-collapse":"collapse",border:"1px solid #111827","font-size":"14px"}},yt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center",color:"#111827"}},vt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},wt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},_t={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},Nt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},St={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},kt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},zt=`
                    * { box-sizing: border-box; margin: 0; padding: 0; }
                    body { font-family: Arial, sans-serif; background: white; color: black; }

                    @page {
                        size: 13in 8.5in landscape;
                        margin: 0;
                    }

                    .page-wrapper {
                        display: flex;
                        flex-direction: row;
                        align-items: flex-start;
                        width: 330.2mm;
                    }

                    .form-copy {
                        width: 82.55mm;
                        height: 215.9mm;
                        overflow: hidden;
                        page-break-inside: avoid;
                        flex-shrink: 0;
                        padding: 4px 3px 0 3px;
                    }

                    /* Horizontal cut line between rows */
                    .cut-line-h {
                        width: 330.2mm;
                        margin:  0;
                        border-top: 1px dashed #666;
                        text-align: center;
                        position: relative;
                    }
                    .cut-line-h span {
                        position: absolute;
                        top: -7px;
                        left: 50%;
                        transform: translateX(-50%);
                        background: white;
                        padding: 0px;
                        font-size: 7px;
                        color: #555;
                        letter-spacing: 1px;
                    }

                    h1 { font-size: 10px; font-weight: bold; }
                    p { font-size: 7px; }
                    table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
                    th, td { border: 1px solid black; padding: 1.5px 2px; text-align: center; font-size: 6.5px; }
                    thead tr { background-color: #e5e7eb; }
                    th { font-weight: bold; }
                    .text-center { text-align: center; }
                    .text-left { text-align: left; }
                    .text-right { text-align: right; }
                    .font-bold { font-weight: bold; }
                    .font-semibold { font-weight: 600; }
                    .italic { font-style: italic; }
                    .grid { display: grid; }
                    .grid-cols-2 { grid-template-columns: 1fr 1fr; gap: 3px; margin-bottom: 4px; }
                    .grid-cols-3 { grid-template-columns: 1fr 1fr 1fr; gap: 6px; margin-top: 6px; }
                    .mb-1 { margin-bottom: 1px; }
                    .mb-2 { margin-bottom: 2px; }
                    .mb-6 { margin-bottom: 4px; }
                    .mb-8 { margin-bottom: 5px; }
                    .mt-8 { margin-top: 6px; }
                    .pb-4 { padding-bottom: 3px; }
                    .border-b-2 { border-bottom: 2px solid black; }
                    .border-t { border-top: 1px solid black; }
                    .h-12 { height: 14px; display: block; }
                    .text-xs { font-size: 6px; }
                    .text-sm { font-size: 7px; }
                    .text-2xl { font-size: 11px; }
                    .space-y-4 > * + * { margin-top: 4px; }
                    .overflow-x-auto { overflow: visible; }
                    .p-8 { padding: 5px; }
                    .p-2 { padding: 1.5px; }
                    .rounded-lg, .rounded { border-radius: 0; }
                    .space-y-4 { display: block; }
`,Mt={__name:"PrintableAttendance",props:{user:Object,selectedYear:Number,selectedMonth:Number,attendanceRecords:Array,companyName:{type:String,default:"Biometric System"},companyLogo:{type:String,default:""},showLogo:{type:Boolean,default:!1},showControls:{type:Boolean,default:!0},calculateUndertime:{type:Boolean,default:void 0},overrides:{type:Array,default:()=>[]}},setup(u,{expose:I}){const w=k(null),y=k(4),_=k(!0),d=u,R=v(()=>typeof d.calculateUndertime=="boolean"?d.calculateUndertime:_.value),Y=v(()=>!d.selectedMonth||!d.selectedYear?"N/A":new Date(d.selectedYear,d.selectedMonth-1).toLocaleDateString("en-US",{month:"long",year:"numeric"}));v(()=>{var e,t;return(t=(e=d.user)==null?void 0:e.officeShift)!=null&&t.schedule?d.user.officeShift.schedule:"N/A"});const H=v(()=>{if(!d.selectedMonth||!d.selectedYear)return[];const e=new Date(d.selectedYear,d.selectedMonth,0).getDate();return Array.from({length:e},(t,o)=>o+1)}),g=e=>{if(!e)return null;if(e instanceof Date){const n=e.getTime();return Number.isNaN(n)?null:e.getHours()*60+e.getMinutes()}const t=String(e).trim();if(!t)return null;const o=t.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*([AaPp][Mm])?$/);if(o){let n=Number(o[1]);const l=Number(o[2]),c=(o[3]||"").toUpperCase();return Number.isNaN(n)||Number.isNaN(l)||(c==="AM"?n===12&&(n=0):c==="PM"&&n<12&&(n+=12),n<0||n>23||l<0||l>59)?null:n*60+l}const s=t.split(":");if(s.length<2)return null;const a=Number(s[0]),i=Number(s[1]);return Number.isNaN(a)||Number.isNaN(i)?null:a*60+i},O=e=>{if(!Number.isFinite(e)||e<=0)return"";const t=Math.floor(e/60),o=e%60;return{hours:t,minutes:o}},E=()=>{var a,i;const e=((a=d.user)==null?void 0:a.office_shift)||((i=d.user)==null?void 0:i.officeShift),o=(Array.isArray(e==null?void 0:e.schedules)?[...e.schedules]:[]).sort((n,l)=>(n.sequence||0)-(l.sequence||0));if(!o.length)return null;const s=o.reduce((n,l)=>{const c=g(l==null?void 0:l.time_in),m=g(l==null?void 0:l.time_out);return c===null||m===null||m<=c?n:n+(m-c)},0);return s>0?s:null},V=e=>{const t=g(e==null?void 0:e.am_in),o=g(e==null?void 0:e.am_out),s=g(e==null?void 0:e.pm_in),a=g(e==null?void 0:e.pm_out);let i=0;return t!==null&&o!==null&&o>t&&(i+=o-t),s!==null&&a!==null&&a>s&&(i+=a-s),i>0?i:null},F=e=>{var a,i;const t=String(e).padStart(2,"0"),o=String(d.selectedMonth).padStart(2,"0"),s=`${d.selectedYear}-${o}-${t}`;return((i=(a=d.attendanceRecords)==null?void 0:a.find)==null?void 0:i.call(a,n=>n.date===s))||null},B=e=>{const t=new Date(e);return Number.isNaN(t.getTime())?"":t.toLocaleTimeString("en-US",{hour:"2-digit",minute:"2-digit",hour12:!0})},j=e=>{const t=new Date(e);if(Number.isNaN(t.getTime()))return null;const o=t.getFullYear(),s=String(t.getMonth()+1).padStart(2,"0"),a=String(t.getDate()).padStart(2,"0");return`${o}-${s}-${a}`},W=e=>{const t=String((e==null?void 0:e.new_checktype)||"").trim().toUpperCase(),o=new Date(e==null?void 0:e.new_checktime);if(Number.isNaN(o.getTime()))return null;const s=o.getHours();return t==="I"?s<12?"am_in":"pm_in":t==="O"?s<=12?"am_out":"pm_out":null},z=(e,t)=>{if(!Array.isArray(d.overrides)||!d.overrides.length)return"";const o=String(e).padStart(2,"0"),s=String(d.selectedMonth).padStart(2,"0"),a=`${d.selectedYear}-${s}-${o}`,i=d.overrides.filter(n=>j(n==null?void 0:n.new_checktime)===a).filter(n=>W(n)===t).sort((n,l)=>{const c=new Date((n==null?void 0:n.updated_at)||(n==null?void 0:n.created_at)||(n==null?void 0:n.new_checktime)).getTime();return new Date((l==null?void 0:l.updated_at)||(l==null?void 0:l.created_at)||(l==null?void 0:l.new_checktime)).getTime()-c});return i.length?B(i[0].new_checktime):""},q=e=>{const t=F(e);if(!t)return null;const o=["am_in","am_out","pm_in","pm_out"],s={...t};return o.forEach(a=>{const i=z(e,a);i&&(s[a]=i)}),s},f=(e,t)=>{if(!d.attendanceRecords||!Array.isArray(d.attendanceRecords))return"";if(t==="am_in"||t==="am_out"||t==="pm_in"||t==="pm_out"){const n=z(e,t);if(n)return n}const o=String(e).padStart(2,"0"),s=String(d.selectedMonth).padStart(2,"0"),a=`${d.selectedYear}-${s}-${o}`,i=d.attendanceRecords.find(n=>n.date===a);if(!i)return"";switch(t){case"am_in":return i.am_in||"";case"am_out":return i.am_out||"";case"pm_in":return i.pm_in||"";case"pm_out":return i.pm_out||"";case"undertime_hrs":return i.undertimeHrs||"";case"undertime_min":return i.undertimeMin||"";default:return""}},M=(e,t)=>{const o=()=>{const c=f(e,"undertime_hrs"),m=f(e,"undertime_min"),h=String(c||"").trim(),N=String(m||"").trim(),S=Number(h),T=Number(N),A=h!==""&&!Number.isNaN(S)&&S>0,G=N!==""&&!Number.isNaN(T)&&T>0;return!A&&!G?"":t==="hrs"?A?String(S):"":N};if(!R.value)return o();const s=q(e);if(!s)return"";const a=E(),i=V(s);if(a===null||i===null)return o();const n=Math.max(0,a-i);if(n<=0)return"";const l=O(n);return l?t==="hrs"?String(l.hours):String(l.minutes).padStart(2,"0"):""},J=()=>{var e;return((e=w.value)==null?void 0:e.innerHTML)||""},K=(e,t)=>{const o=t||1,s=4;let a="";for(let i=0;i<o;i+=s){const n=[];for(let h=0;h<s;h++)i+h<o&&n.push(`<div class="form-copy">${e}</div>`);const l=n.join(""),c=i+s>=o;a+=`<div class="page-wrapper">${l}</div>`}return a},D=(e=y.value||1)=>{var o;const t=(o=w.value)==null?void 0:o.innerHTML;return t?{bodyHtml:K(t,e),styles:zt}:null},X=()=>{const e=D(y.value||1);if(!e)return;const t=window.open("","_blank");t.document.write(`
    <!DOCTYPE html>
    <html>
      <head>
        <meta charset="UTF-8" />
        <title>Daily Time Record</title>
        <style>
                    ${e.styles}
        </style>
      </head>
      <body>
                ${e.bodyHtml}
      </body>
    </html>
  `),t.document.close();const o=()=>{t.focus(),t.print(),t.close()},s=Array.from(t.document.images||[]);if(!s.length){o();return}let a=s.length;const i=()=>{a-=1,a<=0&&o()};s.forEach(n=>{if(n.complete){i();return}n.addEventListener("load",i,{once:!0}),n.addEventListener("error",i,{once:!0})})};return I({getPrintPayload:D,getPrintContent:J}),(e,t)=>{var o,s,a,i;return b(),x("div",nt,[u.showControls?(b(),x("div",rt,[t[4]||(t[4]=r("div",{class:"flex flex-col gap-2"},[r("h3",{class:"text-lg font-semibold text-gray-900 dark:text-white"},"Printable Daily Time Record"),r("p",{class:"text-sm text-gray-600 dark:text-gray-400"},"Print official attendance record for payroll")],-1)),r("div",ot,[r("label",it,[U(r("input",{"onUpdate:modelValue":t[0]||(t[0]=n=>_.value=n),type:"checkbox",class:"h-4 w-4"},null,512),[[Z,_.value]]),t[2]||(t[2]=Q(" Calculate Undertime "))]),r("div",st,[t[3]||(t[3]=r("label",{class:"text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap"},"Copies:",-1)),U(r("select",{"onUpdate:modelValue":t[1]||(t[1]=n=>y.value=n),class:"h-9 px-3 rounded border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-200 font-medium"},[(b(),x($,null,P(10,n=>r("option",{key:n,value:n},p(n),9,at)),64))],512),[[tt,y.value,void 0,{number:!0}]])]),r("button",{onClick:X,class:"px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors flex items-center gap-2"}," Print Record ")])])):C("",!0),r("div",{ref_key:"printContainer",ref:w,style:{background:"white",padding:"0px",border:"1px solid #d1d5db","border-radius":"8px",color:"#111827"}},[r("div",lt,[u.showLogo&&u.companyLogo?(b(),x("div",dt,[r("img",{src:u.companyLogo,alt:"Company logo",style:{"max-height":"50px","object-fit":"contain"}},null,8,ct)])):C("",!0),t[5]||(t[5]=r("p",{style:{"font-size":"8px","font-weight":"600",color:"#374151","margin-bottom":"4px","padding-left":"10px"}},"CSC Form No. 48",-1)),t[6]||(t[6]=r("h1",{style:{"text-align":"center","font-size":"8pt","font-weight":"700",color:"#111827",margin:"0",padding:"0"}}," DAILY TIME RECORD",-1)),r("p",pt,p(u.companyName||"Company / School Name"),1),r("p",ut,p(((o=u.user)==null?void 0:o.department)||((a=(s=u.user)==null?void 0:s.department_ref)==null?void 0:a.department_name)||""),1),r("h1",mt,p((i=u.user)==null?void 0:i.name),1)]),r("div",gt,[r("div",ft,[t[7]||(t[7]=r("p",{style:{color:"#4b5563","font-size":"7.5pt","white-space":"nowrap","padding-right":"5px"}},"For the Month of",-1)),r("p",ht,p(Y.value),1)]),t[8]||(t[8]=L('<div style="line-height:2pt;display:flex;align-items:center;" data-v-8672b573><p style="color:#4b5563;font-size:7.5pt;" data-v-8672b573>Official Hours</p><p style="font-weight:600;color:#111827;" data-v-8672b573></p></div><div style="line-height:8pt;display:flex;align-items:center;" data-v-8672b573><span style="color:#4b5563;font-size:7.5pt;white-space:nowrap;padding-right:5px;" data-v-8672b573> Regular Days </span><span style="flex:1;font-weight:600;color:#111827;border-bottom:1px solid #111827;" data-v-8672b573></span></div><div style="line-height:8pt;display:flex;align-items:center;" data-v-8672b573><p style="color:#4b5563;font-size:7.5pt;" data-v-8672b573>Arrival and Departure</p><p style="font-weight:600;color:#111827;border-bottom:1px solid #111827;" data-v-8672b573></p></div><div style="line-height:8pt;display:flex;align-items:center;" data-v-8672b573><p style="color:#4b5563;font-size:7.5pt;padding-right:10px;" data-v-8672b573>Saturdays</p><p style="width:100%;font-weight:600;color:#111827;border-bottom:1px solid #111827;" data-v-8672b573></p></div>',4))]),r("div",xt,[r("table",bt,[t[10]||(t[10]=r("thead",null,[r("tr",{style:{background:"#f3f4f6"}},[r("th",{rowspan:"2",style:{"font-size":"8.5pt",border:"1px solid #111827",padding:"8px","text-align":"center","vertical-align":"middle","font-weight":"700",color:"#111827"}}," DAY "),r("th",{colspan:"2",style:{"font-size":"8.5pt",border:"1px solid #111827",padding:"8px","text-align":"center","font-weight":"700",color:"#111827"}}," A.M. "),r("th",{colspan:"2",style:{"font-size":"8.5pt",border:"1px solid #111827",padding:"8px","text-align":"center","font-weight":"700",color:"#111827"}}," P.M. "),r("th",{colspan:"2",style:{"font-size":"8.5pt",border:"1px solid #111827",padding:"8px","text-align":"center","font-weight":"700",color:"#111827"}}," UNDERTIME ")]),r("tr",{style:{background:"#f3f4f6"}},[r("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," IN"),r("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," OUT"),r("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," IN"),r("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," OUT"),r("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," Hrs."),r("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," Min.")])],-1)),r("tbody",null,[(b(!0),x($,null,P(H.value,n=>(b(),x("tr",{key:n},[r("td",yt,p(n),1),r("td",vt,p(f(n,"am_in")),1),r("td",wt,p(f(n,"am_out")),1),r("td",_t,p(f(n,"pm_in")),1),r("td",Nt,p(f(n,"pm_out")),1),r("td",St,p(M(n,"hrs")),1),r("td",kt,p(M(n,"min")),1)]))),128)),t[9]||(t[9]=r("tr",{style:{background:"#f3f4f6","font-weight":"700"}},[r("td",{colspan:"7",style:{border:"1px solid #111827",padding:"8px","text-align":"left",color:"#111827"}}," TOTAL ")],-1))])])]),t[11]||(t[11]=L('<div style="color:#374151;" data-v-8672b573><p style="font-style:italic;margin-bottom:16px;font-size:8pt;" data-v-8672b573>     I CERTIFY on my honor that the above is a true and correct report of the hours of work performed, a record of which was made daily at the time of arrival at and departure from office. </p><div style="margin-top:32px;" data-v-8672b573><div style="margin-left:auto;width:50%;text-align:center;" data-v-8672b573><div style="border-top:1px solid #111827;" data-v-8672b573></div></div><div style="" data-v-8672b573><p style="font-style:italic;margin-bottom:16px;font-size:8pt;" data-v-8672b573>     Verified as to the prescribed office hours. </p></div><div style="margin-left:auto;width:50%;text-align:center;" data-v-8672b573><div style="border-top:1px solid #111827;" data-v-8672b573></div><p style="color:#111827;margin-top:0px;font-style:italic;font-size:8pt;" data-v-8672b573>In-Charge</p></div></div></div>',1))],512)])}}},At=et(Mt,[["__scopeId","data-v-8672b573"]]);export{At as P};
