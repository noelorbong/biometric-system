import{y as w,f as b,c as m,o as f,h as H,a as e,p as O,P as L,F as _,j as z,t as l,b as k}from"./app-bjqyF8ns.js";import{_ as U}from"./_plugin-vue_export-helper-DlAUqK2U.js";const V={class:"space-y-4"},E={key:0,class:"flex flex-col md:flex-row gap-4 justify-between items-start md:items-center bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 print:hidden"},F={class:"flex items-center gap-3"},B={class:"flex items-center gap-2"},R=["value"],j={style:{"margin-top":"10px","border-bottom":"2px solid #111827"}},K={style:{"text-align":"center","font-size":"6pt",color:"#4b5563",margin:"0",padding:"0"}},X={style:{"text-align":"center","font-size":"6pt",color:"#4b5563","margin-top":"8pt"}},q={style:{"text-align":"center","font-size":"12pt","font-weight":"700",color:"#111827","margin-top":"5px"}},G={style:{display:"grid","grid-template-columns":"1fr 1fr","column-gap":"16px","row-gap":"10px","margin-bottom":"16px","font-size":"14px"}},J={style:{"line-height":"8pt","grid-column":"span 2",display:"flex","align-items":"center","margin-top":"10px"}},Q={style:{"font-weight":"600","text-align":"center",color:"#111827",width:"100%","font-size":"8pt","border-bottom":"1px solid #111827"}},W={style:{overflow:"visible","margin-bottom":"20px"}},Z={style:{width:"100%","border-collapse":"collapse",border:"1px solid #111827","font-size":"14px"}},tt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center",color:"#111827"}},et={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},ot={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},rt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},nt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},it={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},at={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},st=`
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
`,lt={__name:"PrintableAttendance",props:{user:Object,selectedYear:Number,selectedMonth:Number,attendanceRecords:Array,companyName:{type:String,default:"Biometric System"},showControls:{type:Boolean,default:!0},overrides:{type:Array,default:()=>[]}},setup(p,{expose:S}){const u=w(null),x=w(4),n=p,D=b(()=>!n.selectedMonth||!n.selectedYear?"N/A":new Date(n.selectedYear,n.selectedMonth-1).toLocaleDateString("en-US",{month:"long",year:"numeric"}));b(()=>{var r,t;return(t=(r=n.user)==null?void 0:r.officeShift)!=null&&t.schedule?n.user.officeShift.schedule:"N/A"});const N=b(()=>{if(!n.selectedMonth||!n.selectedYear)return[];const r=new Date(n.selectedYear,n.selectedMonth,0).getDate();return Array.from({length:r},(t,i)=>i+1)}),T=r=>{const t=new Date(r);return Number.isNaN(t.getTime())?"":t.toLocaleTimeString("en-US",{hour:"2-digit",minute:"2-digit",hour12:!0})},M=r=>{const t=new Date(r);if(Number.isNaN(t.getTime()))return null;const i=t.getFullYear(),s=String(t.getMonth()+1).padStart(2,"0"),d=String(t.getDate()).padStart(2,"0");return`${i}-${s}-${d}`},A=r=>{const t=String((r==null?void 0:r.new_checktype)||"").trim().toUpperCase(),i=new Date(r==null?void 0:r.new_checktime);if(Number.isNaN(i.getTime()))return null;const s=i.getHours();return t==="I"?s<12?"am_in":"pm_in":t==="O"?s<=12?"am_out":"pm_out":null},C=(r,t)=>{if(!Array.isArray(n.overrides)||!n.overrides.length)return"";const i=String(r).padStart(2,"0"),s=String(n.selectedMonth).padStart(2,"0"),d=`${n.selectedYear}-${s}-${i}`,a=n.overrides.filter(o=>M(o==null?void 0:o.new_checktime)===d).filter(o=>A(o)===t).sort((o,c)=>{const h=new Date((o==null?void 0:o.updated_at)||(o==null?void 0:o.created_at)||(o==null?void 0:o.new_checktime)).getTime();return new Date((c==null?void 0:c.updated_at)||(c==null?void 0:c.created_at)||(c==null?void 0:c.new_checktime)).getTime()-h});return a.length?T(a[0].new_checktime):""},g=(r,t)=>{if(!n.attendanceRecords||!Array.isArray(n.attendanceRecords))return"";if(t==="am_in"||t==="am_out"||t==="pm_in"||t==="pm_out"){const o=C(r,t);if(o)return o}const i=String(r).padStart(2,"0"),s=String(n.selectedMonth).padStart(2,"0"),d=`${n.selectedYear}-${s}-${i}`,a=n.attendanceRecords.find(o=>o.date===d);if(!a)return"";switch(t){case"am_in":return a.am_in||"";case"am_out":return a.am_out||"";case"pm_in":return a.pm_in||"";case"pm_out":return a.pm_out||"";case"undertime_hrs":return a.undertimeHrs||"";case"undertime_min":return a.undertimeMin||"";default:return""}},P=()=>{var r;return((r=u.value)==null?void 0:r.innerHTML)||""},$=(r,t)=>{const i=t||1,s=4;let d="";for(let a=0;a<i;a+=s){const o=[];for(let y=0;y<s;y++)a+y<i&&o.push(`<div class="form-copy">${r}</div>`);const c=o.join(""),h=a+s>=i;d+=`<div class="page-wrapper">${c}</div>`}return d},v=(r=x.value||1)=>{var i;const t=(i=u.value)==null?void 0:i.innerHTML;return t?{bodyHtml:$(t,r),styles:st}:null},Y=()=>{const r=v(x.value||1);if(!r)return;const t=window.open("","_blank");t.document.write(`
    <!DOCTYPE html>
    <html>
      <head>
        <meta charset="UTF-8" />
        <title>Daily Time Record</title>
        <style>
                    ${r.styles}
        </style>
      </head>
      <body>
                ${r.bodyHtml}
      </body>
    </html>
  `),t.document.close(),t.focus(),t.print(),t.close()};return S({getPrintPayload:v,getPrintContent:P}),(r,t)=>{var i,s,d,a;return f(),m("div",V,[p.showControls?(f(),m("div",E,[t[2]||(t[2]=e("div",{class:"flex flex-col gap-2"},[e("h3",{class:"text-lg font-semibold text-gray-900 dark:text-white"},"Printable Daily Time Record"),e("p",{class:"text-sm text-gray-600 dark:text-gray-400"},"Print official attendance record for payroll")],-1)),e("div",F,[e("div",B,[t[1]||(t[1]=e("label",{class:"text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap"},"Copies:",-1)),O(e("select",{"onUpdate:modelValue":t[0]||(t[0]=o=>x.value=o),class:"h-9 px-3 rounded border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-200 font-medium"},[(f(),m(_,null,z(10,o=>e("option",{key:o,value:o},l(o),9,R)),64))],512),[[L,x.value,void 0,{number:!0}]])]),e("button",{onClick:Y,class:"px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors flex items-center gap-2"}," Print Record ")])])):H("",!0),e("div",{ref_key:"printContainer",ref:u,style:{background:"white",padding:"0px",border:"1px solid #d1d5db","border-radius":"8px",color:"#111827"}},[e("div",j,[t[3]||(t[3]=e("p",{style:{"font-size":"8px","font-weight":"600",color:"#374151","margin-bottom":"4px","padding-left":"10px"}},"CSC Form No. 48",-1)),t[4]||(t[4]=e("h1",{style:{"text-align":"center","font-size":"8pt","font-weight":"700",color:"#111827",margin:"0",padding:"0"}}," DAILY TIME RECORD",-1)),e("p",K,l(p.companyName||"Company / School Name"),1),e("p",X,l(((i=p.user)==null?void 0:i.department)||((d=(s=p.user)==null?void 0:s.department_ref)==null?void 0:d.department_name)||"Department"),1),e("h1",q,l((a=p.user)==null?void 0:a.name),1)]),e("div",G,[e("div",J,[t[5]||(t[5]=e("p",{style:{color:"#4b5563","font-size":"7.5pt","white-space":"nowrap","padding-right":"5px"}},"For the Month of",-1)),e("p",Q,l(D.value),1)]),t[6]||(t[6]=k('<div style="line-height:2pt;display:flex;align-items:center;" data-v-307acc78><p style="color:#4b5563;font-size:7.5pt;" data-v-307acc78>Official Hours</p><p style="font-weight:600;color:#111827;" data-v-307acc78></p></div><div style="line-height:8pt;display:flex;align-items:center;" data-v-307acc78><span style="color:#4b5563;font-size:7.5pt;white-space:nowrap;padding-right:5px;" data-v-307acc78> Regular Days </span><span style="flex:1;font-weight:600;color:#111827;border-bottom:1px solid #111827;" data-v-307acc78></span></div><div style="line-height:8pt;display:flex;align-items:center;" data-v-307acc78><p style="color:#4b5563;font-size:7.5pt;" data-v-307acc78>Arrival and Departure</p><p style="font-weight:600;color:#111827;border-bottom:1px solid #111827;" data-v-307acc78></p></div><div style="line-height:8pt;display:flex;align-items:center;" data-v-307acc78><p style="color:#4b5563;font-size:7.5pt;padding-right:10px;" data-v-307acc78>Saturdays</p><p style="width:100%;font-weight:600;color:#111827;border-bottom:1px solid #111827;" data-v-307acc78></p></div>',4))]),e("div",W,[e("table",Z,[t[8]||(t[8]=e("thead",null,[e("tr",{style:{background:"#f3f4f6"}},[e("th",{rowspan:"2",style:{"font-size":"8.5pt",border:"1px solid #111827",padding:"8px","text-align":"center","vertical-align":"middle","font-weight":"700",color:"#111827"}}," DAY "),e("th",{colspan:"2",style:{"font-size":"8.5pt",border:"1px solid #111827",padding:"8px","text-align":"center","font-weight":"700",color:"#111827"}}," A.M. "),e("th",{colspan:"2",style:{"font-size":"8.5pt",border:"1px solid #111827",padding:"8px","text-align":"center","font-weight":"700",color:"#111827"}}," P.M. "),e("th",{colspan:"2",style:{"font-size":"8.5pt",border:"1px solid #111827",padding:"8px","text-align":"center","font-weight":"700",color:"#111827"}}," UNDERTIME ")]),e("tr",{style:{background:"#f3f4f6"}},[e("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," IN"),e("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," OUT"),e("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," IN"),e("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," OUT"),e("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," Hrs."),e("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," Min.")])],-1)),e("tbody",null,[(f(!0),m(_,null,z(N.value,o=>(f(),m("tr",{key:o},[e("td",tt,l(o),1),e("td",et,l(g(o,"am_in")),1),e("td",ot,l(g(o,"am_out")),1),e("td",rt,l(g(o,"pm_in")),1),e("td",nt,l(g(o,"pm_out")),1),e("td",it,l(g(o,"undertime_hrs")),1),e("td",at,l(g(o,"undertime_min")),1)]))),128)),t[7]||(t[7]=e("tr",{style:{background:"#f3f4f6","font-weight":"700"}},[e("td",{colspan:"7",style:{border:"1px solid #111827",padding:"8px","text-align":"left",color:"#111827"}}," TOTAL ")],-1))])])]),t[9]||(t[9]=k('<div style="color:#374151;" data-v-307acc78><p style="font-style:italic;margin-bottom:16px;font-size:8pt;" data-v-307acc78>     I CERTIFY on my honor that the above is a true and correct report of the hours of work performed, a record of which was made daily at the time of arrival at and departure from office. </p><div style="margin-top:32px;" data-v-307acc78><div style="margin-left:auto;width:50%;text-align:center;" data-v-307acc78><div style="border-top:1px solid #111827;" data-v-307acc78></div></div><div style="" data-v-307acc78><p style="font-style:italic;margin-bottom:16px;font-size:8pt;" data-v-307acc78>     Verified as to the prescribed office hours. </p></div><div style="margin-left:auto;width:50%;text-align:center;" data-v-307acc78><div style="border-top:1px solid #111827;" data-v-307acc78></div><p style="color:#111827;margin-top:0px;font-style:italic;font-size:8pt;" data-v-307acc78>In-Charge</p></div></div></div>',1))],512)])}}},pt=U(lt,[["__scopeId","data-v-307acc78"]]);export{pt as P};
