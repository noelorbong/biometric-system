import{o as x,c as y,a as o,m as C,J as G,j as Z,Q as tt,F as U,i as $,t as p,h as P,b as L,A as S,p as k}from"./app-9IlE4lGT.js";import{_ as et}from"./_plugin-vue_export-helper-DlAUqK2U.js";const nt={class:"space-y-4"},ot={key:0,class:"flex flex-col md:flex-row gap-4 justify-between items-start md:items-center bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 print:hidden"},rt={class:"flex items-center gap-3"},it={class:"flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap"},at={class:"flex items-center gap-2"},st=["value"],lt={style:{"margin-top":"10px","border-bottom":"2px solid #111827",position:"relative"}},dt={key:0,style:{position:"absolute",top:"0",right:"0",display:"flex","padding-right":"10px","justify-content":"center","align-items":"center","margin-bottom":"4px"}},ct=["src"],pt={style:{"text-align":"center","font-size":"6pt",color:"#4b5563",margin:"0",padding:"0"}},ut={style:{"text-align":"center","font-size":"6pt",color:"#4b5563","margin-top":"8pt"}},mt={style:{"text-align":"center","font-size":"12pt","font-weight":"700",color:"#111827","margin-top":"5px"}},gt={style:{display:"grid","grid-template-columns":"1fr 1fr","column-gap":"16px","row-gap":"10px","margin-bottom":"16px","font-size":"14px"}},ft={style:{"line-height":"8pt","grid-column":"span 2",display:"flex","align-items":"center","margin-top":"10px"}},ht={style:{"font-weight":"600","text-align":"center",color:"#111827",width:"100%","font-size":"8pt","border-bottom":"1px solid #111827"}},xt={style:{overflow:"visible","margin-bottom":"20px"}},yt={style:{width:"100%","border-collapse":"collapse",border:"1px solid #111827","font-size":"14px"}},bt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center",color:"#111827"}},vt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},wt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},_t={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},Nt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},St={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},kt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},zt=`
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
`,Mt={__name:"PrintableAttendance",props:{user:Object,selectedYear:Number,selectedMonth:Number,attendanceRecords:Array,companyName:{type:String,default:"Biometric System"},companyLogo:{type:String,default:""},showLogo:{type:Boolean,default:!1},showControls:{type:Boolean,default:!0},calculateUndertime:{type:Boolean,default:void 0},overrides:{type:Array,default:()=>[]}},setup(u,{expose:I}){const v=S(null),b=S(4),w=S(!1),d=u,R=k(()=>typeof d.calculateUndertime=="boolean"?d.calculateUndertime:w.value),Y=k(()=>!d.selectedMonth||!d.selectedYear?"N/A":new Date(d.selectedYear,d.selectedMonth-1).toLocaleDateString("en-US",{month:"long",year:"numeric"})),H=k(()=>{if(!d.selectedMonth||!d.selectedYear)return[];const e=new Date(d.selectedYear,d.selectedMonth,0).getDate();return Array.from({length:e},(t,r)=>r+1)}),g=e=>{if(!e)return null;if(e instanceof Date){const n=e.getTime();return Number.isNaN(n)?null:e.getHours()*60+e.getMinutes()}const t=String(e).trim();if(!t)return null;const r=t.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*([AaPp][Mm])?$/);if(r){let n=Number(r[1]);const l=Number(r[2]),c=(r[3]||"").toUpperCase();return Number.isNaN(n)||Number.isNaN(l)||(c==="AM"?n===12&&(n=0):c==="PM"&&n<12&&(n+=12),n<0||n>23||l<0||l>59)?null:n*60+l}const a=t.split(":");if(a.length<2)return null;const s=Number(a[0]),i=Number(a[1]);return Number.isNaN(s)||Number.isNaN(i)?null:s*60+i},O=e=>{if(!Number.isFinite(e)||e<=0)return"";const t=Math.floor(e/60),r=e%60;return{hours:t,minutes:r}},E=()=>{var s,i;const e=((s=d.user)==null?void 0:s.office_shift)||((i=d.user)==null?void 0:i.officeShift),r=(Array.isArray(e==null?void 0:e.schedules)?[...e.schedules]:[]).sort((n,l)=>(n.sequence||0)-(l.sequence||0));if(!r.length)return null;const a=r.reduce((n,l)=>{const c=g(l==null?void 0:l.time_in),m=g(l==null?void 0:l.time_out);return c===null||m===null||m<=c?n:n+(m-c)},0);return a>0?a:null},V=e=>{const t=g(e==null?void 0:e.am_in),r=g(e==null?void 0:e.am_out),a=g(e==null?void 0:e.pm_in),s=g(e==null?void 0:e.pm_out);let i=0;return t!==null&&r!==null&&r>t&&(i+=r-t),a!==null&&s!==null&&s>a&&(i+=s-a),i>0?i:null},F=e=>{var s,i;const t=String(e).padStart(2,"0"),r=String(d.selectedMonth).padStart(2,"0"),a=`${d.selectedYear}-${r}-${t}`;return((i=(s=d.attendanceRecords)==null?void 0:s.find)==null?void 0:i.call(s,n=>n.date===a))||null},B=e=>{const t=new Date(e);return Number.isNaN(t.getTime())?"":t.toLocaleTimeString("en-US",{hour:"2-digit",minute:"2-digit",hour12:!0})},j=e=>{const t=new Date(e);if(Number.isNaN(t.getTime()))return null;const r=t.getFullYear(),a=String(t.getMonth()+1).padStart(2,"0"),s=String(t.getDate()).padStart(2,"0");return`${r}-${a}-${s}`},W=e=>{const t=String((e==null?void 0:e.new_checktype)||"").trim().toUpperCase(),r=new Date(e==null?void 0:e.new_checktime);if(Number.isNaN(r.getTime()))return null;const a=r.getHours();return t==="I"?a<12?"am_in":"pm_in":t==="O"?a<=12?"am_out":"pm_out":null},z=(e,t)=>{if(!Array.isArray(d.overrides)||!d.overrides.length)return"";const r=String(e).padStart(2,"0"),a=String(d.selectedMonth).padStart(2,"0"),s=`${d.selectedYear}-${a}-${r}`,i=d.overrides.filter(n=>j(n==null?void 0:n.new_checktime)===s).filter(n=>W(n)===t).sort((n,l)=>{const c=new Date((n==null?void 0:n.updated_at)||(n==null?void 0:n.created_at)||(n==null?void 0:n.new_checktime)).getTime();return new Date((l==null?void 0:l.updated_at)||(l==null?void 0:l.created_at)||(l==null?void 0:l.new_checktime)).getTime()-c});return i.length?B(i[0].new_checktime):""},q=e=>{const t=F(e);if(!t)return null;const r=["am_in","am_out","pm_in","pm_out"],a={...t};return r.forEach(s=>{const i=z(e,s);i&&(a[s]=i)}),a},f=(e,t)=>{if(!d.attendanceRecords||!Array.isArray(d.attendanceRecords))return"";if(t==="am_in"||t==="am_out"||t==="pm_in"||t==="pm_out"){const n=z(e,t);if(n)return n}const r=String(e).padStart(2,"0"),a=String(d.selectedMonth).padStart(2,"0"),s=`${d.selectedYear}-${a}-${r}`,i=d.attendanceRecords.find(n=>n.date===s);if(!i)return"";switch(t){case"am_in":return i.am_in||"";case"am_out":return i.am_out||"";case"pm_in":return i.pm_in||"";case"pm_out":return i.pm_out||"";case"undertime_hrs":return i.undertimeHrs||"";case"undertime_min":return i.undertimeMin||"";default:return""}},M=(e,t)=>{const r=()=>{const c=f(e,"undertime_hrs"),m=f(e,"undertime_min"),h=String(c||"").trim(),_=String(m||"").trim(),N=Number(h),T=Number(_),A=h!==""&&!Number.isNaN(N)&&N>0,X=_!==""&&!Number.isNaN(T)&&T>0;return!A&&!X?"":t==="hrs"?A?String(N):"":_};if(!R.value)return r();const a=q(e);if(!a)return"";const s=E(),i=V(a);if(s===null||i===null)return r();const n=Math.max(0,s-i);if(n<=0)return"";const l=O(n);return l?t==="hrs"?String(l.hours):String(l.minutes).padStart(2,"0"):""},J=()=>{var e;return((e=v.value)==null?void 0:e.innerHTML)||""},K=(e,t)=>{const r=t||1,a=4;let s="";for(let i=0;i<r;i+=a){const n=[];for(let h=0;h<a;h++)i+h<r&&n.push(`<div class="form-copy">${e}</div>`);const l=n.join(""),c=i+a>=r;s+=`<div class="page-wrapper">${l}</div>`}return s},D=(e=b.value||1)=>{var r;const t=(r=v.value)==null?void 0:r.innerHTML;return t?{bodyHtml:K(t,e),styles:zt}:null},Q=()=>{const e=D(b.value||1);if(!e)return;const t=window.open("","_blank");t.document.write(`
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
  `),t.document.close();const r=()=>{t.focus(),t.print(),t.close()},a=Array.from(t.document.images||[]);if(!a.length){r();return}let s=a.length;const i=()=>{s-=1,s<=0&&r()};a.forEach(n=>{if(n.complete){i();return}n.addEventListener("load",i,{once:!0}),n.addEventListener("error",i,{once:!0})})};return I({getPrintPayload:D,getPrintContent:J}),(e,t)=>{var r,a,s,i;return x(),y("div",nt,[u.showControls?(x(),y("div",ot,[t[4]||(t[4]=o("div",{class:"flex flex-col gap-2"},[o("h3",{class:"text-lg font-semibold text-gray-900 dark:text-white"},"Printable Daily Time Record"),o("p",{class:"text-sm text-gray-600 dark:text-gray-400"},"Print official attendance record for payroll")],-1)),o("div",rt,[o("label",it,[C(o("input",{"onUpdate:modelValue":t[0]||(t[0]=n=>w.value=n),type:"checkbox",class:"h-4 w-4"},null,512),[[G,w.value]]),t[2]||(t[2]=Z(" Calculate Undertime ",-1))]),o("div",at,[t[3]||(t[3]=o("label",{class:"text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap"},"Copies:",-1)),C(o("select",{"onUpdate:modelValue":t[1]||(t[1]=n=>b.value=n),class:"h-9 px-3 rounded border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-200 font-medium"},[(x(),y(U,null,$(10,n=>o("option",{key:n,value:n},p(n),9,st)),64))],512),[[tt,b.value,void 0,{number:!0}]])]),o("button",{onClick:Q,class:"px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors flex items-center gap-2"}," Print Record ")])])):P("",!0),o("div",{ref_key:"printContainer",ref:v,style:{background:"white",padding:"0px",border:"1px solid #d1d5db","border-radius":"8px",color:"#111827"}},[o("div",lt,[u.showLogo&&u.companyLogo?(x(),y("div",dt,[o("img",{src:u.companyLogo,alt:"Company logo",style:{"max-height":"50px","object-fit":"contain"}},null,8,ct)])):P("",!0),t[5]||(t[5]=o("p",{style:{"font-size":"8px","font-weight":"600",color:"#374151","margin-bottom":"4px","padding-left":"10px"}},"CSC Form No. 48",-1)),t[6]||(t[6]=o("h1",{style:{"text-align":"center","font-size":"8pt","font-weight":"700",color:"#111827",margin:"0",padding:"0"}}," DAILY TIME RECORD",-1)),o("p",pt,p(u.companyName||"Company / School Name"),1),o("p",ut,p(((r=u.user)==null?void 0:r.department)||((s=(a=u.user)==null?void 0:a.department_ref)==null?void 0:s.department_name)||""),1),o("h1",mt,p((i=u.user)==null?void 0:i.name),1)]),o("div",gt,[o("div",ft,[t[7]||(t[7]=o("p",{style:{color:"#4b5563","font-size":"7.5pt","white-space":"nowrap","padding-right":"5px"}},"For the Month of",-1)),o("p",ht,p(Y.value),1)]),t[8]||(t[8]=L('<div style="line-height:2pt;display:flex;align-items:center;" data-v-ad71aa74><p style="color:#4b5563;font-size:7.5pt;" data-v-ad71aa74>Official Hours</p><p style="font-weight:600;color:#111827;" data-v-ad71aa74></p></div><div style="line-height:8pt;display:flex;align-items:center;" data-v-ad71aa74><span style="color:#4b5563;font-size:7.5pt;white-space:nowrap;padding-right:5px;" data-v-ad71aa74> Regular Days </span><span style="flex:1;font-weight:600;color:#111827;border-bottom:1px solid #111827;" data-v-ad71aa74></span></div><div style="line-height:8pt;display:flex;align-items:center;" data-v-ad71aa74><p style="color:#4b5563;font-size:7.5pt;" data-v-ad71aa74>Arrival and Departure</p><p style="font-weight:600;color:#111827;border-bottom:1px solid #111827;" data-v-ad71aa74></p></div><div style="line-height:8pt;display:flex;align-items:center;" data-v-ad71aa74><p style="color:#4b5563;font-size:7.5pt;padding-right:10px;" data-v-ad71aa74>Saturdays</p><p style="width:100%;font-weight:600;color:#111827;border-bottom:1px solid #111827;" data-v-ad71aa74></p></div>',4))]),o("div",xt,[o("table",yt,[t[10]||(t[10]=o("thead",null,[o("tr",{style:{background:"#f3f4f6"}},[o("th",{rowspan:"2",style:{"font-size":"8.5pt",border:"1px solid #111827",padding:"8px","text-align":"center","vertical-align":"middle","font-weight":"700",color:"#111827"}}," DAY "),o("th",{colspan:"2",style:{"font-size":"8.5pt",border:"1px solid #111827",padding:"8px","text-align":"center","font-weight":"700",color:"#111827"}}," A.M. "),o("th",{colspan:"2",style:{"font-size":"8.5pt",border:"1px solid #111827",padding:"8px","text-align":"center","font-weight":"700",color:"#111827"}}," P.M. "),o("th",{colspan:"2",style:{"font-size":"8.5pt",border:"1px solid #111827",padding:"8px","text-align":"center","font-weight":"700",color:"#111827"}}," UNDERTIME ")]),o("tr",{style:{background:"#f3f4f6"}},[o("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," IN"),o("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," OUT"),o("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," IN"),o("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," OUT"),o("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," Hrs."),o("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," Min.")])],-1)),o("tbody",null,[(x(!0),y(U,null,$(H.value,n=>(x(),y("tr",{key:n},[o("td",bt,p(n),1),o("td",vt,p(f(n,"am_in")),1),o("td",wt,p(f(n,"am_out")),1),o("td",_t,p(f(n,"pm_in")),1),o("td",Nt,p(f(n,"pm_out")),1),o("td",St,p(M(n,"hrs")),1),o("td",kt,p(M(n,"min")),1)]))),128)),t[9]||(t[9]=o("tr",{style:{background:"#f3f4f6","font-weight":"700"}},[o("td",{colspan:"7",style:{border:"1px solid #111827",padding:"8px","text-align":"left",color:"#111827"}}," TOTAL ")],-1))])])]),t[11]||(t[11]=L('<div style="color:#374151;" data-v-ad71aa74><p style="font-style:italic;margin-bottom:16px;font-size:8pt;" data-v-ad71aa74>     I CERTIFY on my honor that the above is a true and correct report of the hours of work performed, a record of which was made daily at the time of arrival at and departure from office. </p><div style="margin-top:32px;" data-v-ad71aa74><div style="margin-left:auto;width:50%;text-align:center;" data-v-ad71aa74><div style="border-top:1px solid #111827;" data-v-ad71aa74></div></div><div style="" data-v-ad71aa74><p style="font-style:italic;margin-bottom:16px;font-size:8pt;" data-v-ad71aa74>     Verified as to the prescribed office hours. </p></div><div style="margin-left:auto;width:50%;text-align:center;" data-v-ad71aa74><div style="border-top:1px solid #111827;" data-v-ad71aa74></div><p style="color:#111827;margin-top:0px;font-style:italic;font-size:8pt;" data-v-ad71aa74>In-Charge</p></div></div></div>',1))],512)])}}},At=et(Mt,[["__scopeId","data-v-ad71aa74"]]);export{At as P};
