import{o as g,c as f,a as o,m as U,L as J,j as Q,S as Z,F as $,i as L,t as p,h as N,b as tt,A as k,p as z}from"./app-oJB5iMvq.js";import{_ as et}from"./_plugin-vue_export-helper-DlAUqK2U.js";const nt={class:"space-y-4"},ot={key:0,class:"flex flex-col md:flex-row gap-4 justify-between items-start md:items-center bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 print:hidden"},rt={class:"flex items-center gap-3"},it={class:"flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap"},st={class:"flex items-center gap-2"},at=["value"],lt={style:{"margin-top":"10px","border-bottom":"2px solid #111827",position:"relative"}},dt={key:0,style:{position:"absolute",top:"0",right:"0",display:"flex","padding-right":"10px","justify-content":"center","align-items":"center","margin-bottom":"4px"}},ct=["src"],pt={style:{"text-align":"center","font-size":"6pt",color:"#4b5563",margin:"0",padding:"0"}},ut={style:{"text-align":"center","font-size":"6pt",color:"#4b5563","margin-top":"8pt"}},mt={style:{"text-align":"center","font-size":"12pt","font-weight":"700",color:"#111827","margin-top":"5px"}},gt={style:{display:"grid","grid-template-columns":"1fr 1fr","column-gap":"16px","row-gap":"10px","margin-bottom":"16px","font-size":"14px"}},ft={style:{"line-height":"8pt","grid-column":"span 2",display:"flex","align-items":"center","margin-top":"10px"}},ht={style:{"font-weight":"600","text-align":"center",color:"#111827",width:"100%","font-size":"8pt","border-bottom":"1px solid #111827"}},xt={style:{overflow:"visible","margin-bottom":"20px"}},yt={style:{width:"100%","border-collapse":"collapse",border:"1px solid #111827","font-size":"14px"}},bt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center",color:"#111827"}},wt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},vt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},_t={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},St={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},Nt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},kt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},zt={style:{color:"#374151"}},Mt={style:{"margin-top":"32px"}},Dt={style:{"margin-left":"auto",width:"50%","text-align":"center"}},Tt={key:0,style:{display:"flex","justify-content":"center","margin-bottom":"6px"}},At=["src"],Ct={style:{"font-size":"7pt","text-transform":"uppercase"}},Ut=`
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
`,$t={__name:"PrintableAttendance",props:{user:Object,selectedYear:Number,selectedMonth:Number,attendanceRecords:Array,companyName:{type:String,default:"Biometric System"},companyLogo:{type:String,default:""},showLogo:{type:Boolean,default:!1},showControls:{type:Boolean,default:!0},signatoryName:{type:String,default:""},signatorySignature:{type:String,default:""},signatorySignatureEnabled:{type:Boolean,default:!1},calculateUndertime:{type:Boolean,default:void 0},overrides:{type:Array,default:()=>[]}},setup(c,{expose:P}){const w=k(null),b=k(4),v=k(!1),d=c,E=z(()=>typeof d.calculateUndertime=="boolean"?d.calculateUndertime:v.value),I=z(()=>!d.selectedMonth||!d.selectedYear?"N/A":new Date(d.selectedYear,d.selectedMonth-1).toLocaleDateString("en-US",{month:"long",year:"numeric"})),R=z(()=>{if(!d.selectedMonth||!d.selectedYear)return[];const e=new Date(d.selectedYear,d.selectedMonth,0).getDate();return Array.from({length:e},(t,r)=>r+1)}),h=e=>{if(!e)return null;if(e instanceof Date){const n=e.getTime();return Number.isNaN(n)?null:e.getHours()*60+e.getMinutes()}const t=String(e).trim();if(!t)return null;const r=t.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*([AaPp][Mm])?$/);if(r){let n=Number(r[1]);const l=Number(r[2]),u=(r[3]||"").toUpperCase();return Number.isNaN(n)||Number.isNaN(l)||(u==="AM"?n===12&&(n=0):u==="PM"&&n<12&&(n+=12),n<0||n>23||l<0||l>59)?null:n*60+l}const s=t.split(":");if(s.length<2)return null;const a=Number(s[0]),i=Number(s[1]);return Number.isNaN(a)||Number.isNaN(i)?null:a*60+i},Y=e=>{if(!Number.isFinite(e)||e<=0)return"";const t=Math.floor(e/60),r=e%60;return{hours:t,minutes:r}},H=()=>{var a,i;const e=((a=d.user)==null?void 0:a.office_shift)||((i=d.user)==null?void 0:i.officeShift),r=(Array.isArray(e==null?void 0:e.schedules)?[...e.schedules]:[]).sort((n,l)=>(n.sequence||0)-(l.sequence||0));if(!r.length)return null;const s=r.reduce((n,l)=>{const u=h(l==null?void 0:l.time_in),m=h(l==null?void 0:l.time_out);return u===null||m===null||m<=u?n:n+(m-u)},0);return s>0?s:null},O=e=>{const t=h(e==null?void 0:e.am_in),r=h(e==null?void 0:e.am_out),s=h(e==null?void 0:e.pm_in),a=h(e==null?void 0:e.pm_out);let i=0;return t!==null&&r!==null&&r>t&&(i+=r-t),s!==null&&a!==null&&a>s&&(i+=a-s),i>0?i:null},V=e=>{var a,i;const t=String(e).padStart(2,"0"),r=String(d.selectedMonth).padStart(2,"0"),s=`${d.selectedYear}-${r}-${t}`;return((i=(a=d.attendanceRecords)==null?void 0:a.find)==null?void 0:i.call(a,n=>n.date===s))||null},F=e=>{const t=new Date(e);return Number.isNaN(t.getTime())?"":t.toLocaleTimeString("en-US",{hour:"2-digit",minute:"2-digit",hour12:!0})},j=e=>{const t=new Date(e);if(Number.isNaN(t.getTime()))return null;const r=t.getFullYear(),s=String(t.getMonth()+1).padStart(2,"0"),a=String(t.getDate()).padStart(2,"0");return`${r}-${s}-${a}`},B=e=>{const t=String((e==null?void 0:e.new_checktype)||"").trim().toUpperCase(),r=new Date(e==null?void 0:e.new_checktime);if(Number.isNaN(r.getTime()))return null;const s=r.getHours();return t==="I"?s<12?"am_in":"pm_in":t==="O"?s<=12?"am_out":"pm_out":null},M=(e,t)=>{if(!Array.isArray(d.overrides)||!d.overrides.length)return"";const r=String(e).padStart(2,"0"),s=String(d.selectedMonth).padStart(2,"0"),a=`${d.selectedYear}-${s}-${r}`,i=d.overrides.filter(n=>j(n==null?void 0:n.new_checktime)===a).filter(n=>B(n)===t).sort((n,l)=>{const u=new Date((n==null?void 0:n.updated_at)||(n==null?void 0:n.created_at)||(n==null?void 0:n.new_checktime)).getTime();return new Date((l==null?void 0:l.updated_at)||(l==null?void 0:l.created_at)||(l==null?void 0:l.new_checktime)).getTime()-u});return i.length?F(i[0].new_checktime):""},W=e=>{const t=V(e);if(!t)return null;const r=["am_in","am_out","pm_in","pm_out"],s={...t};return r.forEach(a=>{const i=M(e,a);i&&(s[a]=i)}),s},x=(e,t)=>{if(!d.attendanceRecords||!Array.isArray(d.attendanceRecords))return"";if(t==="am_in"||t==="am_out"||t==="pm_in"||t==="pm_out"){const n=M(e,t);if(n)return n}const r=String(e).padStart(2,"0"),s=String(d.selectedMonth).padStart(2,"0"),a=`${d.selectedYear}-${s}-${r}`,i=d.attendanceRecords.find(n=>n.date===a);if(!i)return"";switch(t){case"am_in":return i.am_in||"";case"am_out":return i.am_out||"";case"pm_in":return i.pm_in||"";case"pm_out":return i.pm_out||"";case"undertime_hrs":return i.undertimeHrs||"";case"undertime_min":return i.undertimeMin||"";default:return""}},D=(e,t)=>{const r=()=>{const u=x(e,"undertime_hrs"),m=x(e,"undertime_min"),y=String(u||"").trim(),_=String(m||"").trim(),S=Number(y),A=Number(_),C=y!==""&&!Number.isNaN(S)&&S>0,G=_!==""&&!Number.isNaN(A)&&A>0;return!C&&!G?"":t==="hrs"?C?String(S):"":_};if(!E.value)return r();const s=W(e);if(!s)return"";const a=H(),i=O(s);if(a===null||i===null)return r();const n=Math.max(0,a-i);if(n<=0)return"";const l=Y(n);return l?t==="hrs"?String(l.hours):String(l.minutes).padStart(2,"0"):""},q=()=>{var e;return((e=w.value)==null?void 0:e.innerHTML)||""},K=(e,t)=>{const r=t||1,s=4;let a="";for(let i=0;i<r;i+=s){const n=[];for(let y=0;y<s;y++)i+y<r&&n.push(`<div class="form-copy">${e}</div>`);const l=n.join(""),u=i+s>=r;a+=`<div class="page-wrapper">${l}</div>`}return a},T=(e=b.value||1)=>{var r;const t=(r=w.value)==null?void 0:r.innerHTML;return t?{bodyHtml:K(t,e),styles:Ut}:null},X=()=>{const e=T(b.value||1);if(!e)return;const t=window.open("","_blank");t.document.write(`
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
  `),t.document.close();const r=()=>{t.focus(),t.print(),t.close()},s=Array.from(t.document.images||[]);if(!s.length){r();return}let a=s.length;const i=()=>{a-=1,a<=0&&r()};s.forEach(n=>{if(n.complete){i();return}n.addEventListener("load",i,{once:!0}),n.addEventListener("error",i,{once:!0})})};return P({getPrintPayload:T,getPrintContent:q}),(e,t)=>{var r,s,a,i;return g(),f("div",nt,[c.showControls?(g(),f("div",ot,[t[4]||(t[4]=o("div",{class:"flex flex-col gap-2"},[o("h3",{class:"text-lg font-semibold text-gray-900 dark:text-white"},"Printable Daily Time Record"),o("p",{class:"text-sm text-gray-600 dark:text-gray-400"},"Print official attendance record for payroll")],-1)),o("div",rt,[o("label",it,[U(o("input",{"onUpdate:modelValue":t[0]||(t[0]=n=>v.value=n),type:"checkbox",class:"h-4 w-4"},null,512),[[J,v.value]]),t[2]||(t[2]=Q(" Calculate Undertime ",-1))]),o("div",st,[t[3]||(t[3]=o("label",{class:"text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap"},"Copies:",-1)),U(o("select",{"onUpdate:modelValue":t[1]||(t[1]=n=>b.value=n),class:"h-9 px-3 rounded border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-200 font-medium"},[(g(),f($,null,L(10,n=>o("option",{key:n,value:n},p(n),9,at)),64))],512),[[Z,b.value,void 0,{number:!0}]])]),o("button",{onClick:X,class:"px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors flex items-center gap-2"}," Print Record ")])])):N("",!0),o("div",{ref_key:"printContainer",ref:w,style:{background:"white",padding:"0px",border:"1px solid #d1d5db","border-radius":"8px",color:"#111827"}},[o("div",lt,[c.showLogo&&c.companyLogo?(g(),f("div",dt,[o("img",{src:c.companyLogo,alt:"Company logo",style:{"max-height":"50px","object-fit":"contain"}},null,8,ct)])):N("",!0),t[5]||(t[5]=o("p",{style:{"font-size":"8px","font-weight":"600",color:"#374151","margin-bottom":"4px","padding-left":"10px"}},"CSC Form No. 48",-1)),t[6]||(t[6]=o("h1",{style:{"text-align":"center","font-size":"8pt","font-weight":"700",color:"#111827",margin:"0",padding:"0"}}," DAILY TIME RECORD",-1)),o("p",pt,p(c.companyName||"Company / School Name"),1),o("p",ut,p(((r=c.user)==null?void 0:r.department)||((a=(s=c.user)==null?void 0:s.department_ref)==null?void 0:a.department_name)||""),1),o("h1",mt,p((i=c.user)==null?void 0:i.name),1)]),o("div",gt,[o("div",ft,[t[7]||(t[7]=o("p",{style:{color:"#4b5563","font-size":"7.5pt","white-space":"nowrap","padding-right":"5px"}},"For the Month of",-1)),o("p",ht,p(I.value),1)]),t[8]||(t[8]=tt('<div style="line-height:2pt;display:flex;align-items:center;" data-v-dcaa308e><p style="color:#4b5563;font-size:7.5pt;" data-v-dcaa308e>Official Hours</p><p style="font-weight:600;color:#111827;" data-v-dcaa308e></p></div><div style="line-height:8pt;display:flex;align-items:center;" data-v-dcaa308e><span style="color:#4b5563;font-size:7.5pt;white-space:nowrap;padding-right:5px;" data-v-dcaa308e> Regular Days </span><span style="flex:1;font-weight:600;color:#111827;border-bottom:1px solid #111827;" data-v-dcaa308e></span></div><div style="line-height:8pt;display:flex;align-items:center;" data-v-dcaa308e><p style="color:#4b5563;font-size:7.5pt;" data-v-dcaa308e>Arrival and Departure</p><p style="font-weight:600;color:#111827;border-bottom:1px solid #111827;" data-v-dcaa308e></p></div><div style="line-height:8pt;display:flex;align-items:center;" data-v-dcaa308e><p style="color:#4b5563;font-size:7.5pt;padding-right:10px;" data-v-dcaa308e>Saturdays</p><p style="width:100%;font-weight:600;color:#111827;border-bottom:1px solid #111827;" data-v-dcaa308e></p></div>',4))]),o("div",xt,[o("table",yt,[t[10]||(t[10]=o("thead",null,[o("tr",{style:{background:"#f3f4f6"}},[o("th",{rowspan:"2",style:{"font-size":"8.5pt",border:"1px solid #111827",padding:"8px","text-align":"center","vertical-align":"middle","font-weight":"700",color:"#111827"}}," DAY "),o("th",{colspan:"2",style:{"font-size":"8.5pt",border:"1px solid #111827",padding:"8px","text-align":"center","font-weight":"700",color:"#111827"}}," A.M. "),o("th",{colspan:"2",style:{"font-size":"8.5pt",border:"1px solid #111827",padding:"8px","text-align":"center","font-weight":"700",color:"#111827"}}," P.M. "),o("th",{colspan:"2",style:{"font-size":"8.5pt",border:"1px solid #111827",padding:"8px","text-align":"center","font-weight":"700",color:"#111827"}}," UNDERTIME ")]),o("tr",{style:{background:"#f3f4f6"}},[o("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," IN"),o("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," OUT"),o("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," IN"),o("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," OUT"),o("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," Hrs."),o("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," Min.")])],-1)),o("tbody",null,[(g(!0),f($,null,L(R.value,n=>(g(),f("tr",{key:n},[o("td",bt,p(n),1),o("td",wt,p(x(n,"am_in")),1),o("td",vt,p(x(n,"am_out")),1),o("td",_t,p(x(n,"pm_in")),1),o("td",St,p(x(n,"pm_out")),1),o("td",Nt,p(D(n,"hrs")),1),o("td",kt,p(D(n,"min")),1)]))),128)),t[9]||(t[9]=o("tr",{style:{background:"#f3f4f6","font-weight":"700"}},[o("td",{colspan:"7",style:{border:"1px solid #111827",padding:"8px","text-align":"left",color:"#111827"}}," TOTAL ")],-1))])])]),o("div",zt,[t[15]||(t[15]=o("p",{style:{"font-style":"italic","margin-bottom":"16px","font-size":"8pt"}},"     I CERTIFY on my honor that the above is a true and correct report of the hours of work performed, a record of which was made daily at the time of arrival at and departure from office. ",-1)),o("div",Mt,[t[13]||(t[13]=o("div",{style:{"margin-left":"auto",width:"50%","text-align":"center"}},[o("div",{style:{"border-top":"1px solid #111827"}})],-1)),t[14]||(t[14]=o("div",{style:{}},[o("p",{style:{"font-style":"italic","margin-bottom":"16px","font-size":"8pt"}},"     Verified as to the prescribed office hours. ")],-1)),o("div",Dt,[c.signatorySignatureEnabled&&c.signatorySignature?(g(),f("div",Tt,[o("img",{src:c.signatorySignature,alt:"Signatory e-signature",style:{"max-height":"40px","max-width":"160px","object-fit":"contain"}},null,8,At)])):N("",!0),o("p",Ct,p(c.signatoryName),1),t[11]||(t[11]=o("div",{style:{"border-top":"1px solid #111827"}},null,-1)),t[12]||(t[12]=o("p",{style:{color:"#111827","margin-top":"0px","font-style":"italic","font-size":"8pt"}},"In-Charge",-1))])])])],512)])}}},Et=et($t,[["__scopeId","data-v-dcaa308e"]]);export{Et as P};
