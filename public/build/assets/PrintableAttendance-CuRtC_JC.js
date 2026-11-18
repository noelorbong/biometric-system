import{b as c,c as f,d as t,H as V,f as k,t as p,q as H,e as S,F as N,y as D,D as z,B,N as nt,W as ot,z as it,h as st,r as L}from"./app-2fZFf2jm.js";import{f as at}from"./printPreview-DQFRcr1D.js";import{u as lt}from"./AppSettingStore-D2Ks4f2H.js";import{_ as dt}from"./_plugin-vue_export-helper-DlAUqK2U.js";const mt=["src"],pt={class:"dtr-two-company"},ut={key:1,class:"dtr-two-department"},ct={key:2,class:"dtr-two-address"},gt={class:"dtr-two-info"},ft={class:"dtr-two-name"},ht={class:"dtr-two-month"},yt={class:"dtr-two-dated"},bt={class:"dtr-two-employee"},wt={class:"dtr-two-footer"},xt={class:"dtr-two-approved"},vt={class:"dtr-two-signature"},St=["src"],zt={class:"dtr-two-keeper-signature"},kt=["src"],Nt={__name:"DtrTemplateTwo",props:{user:Object,layoutSettings:{type:Object,default:()=>({})},companyName:String,headerAddress:String,companyLogo:String,monthYear:String,days:Array,recordValue:Function,undertimeValue:Function,timekeeperName:String,timekeeperSignature:String,signatoryName:String,signatorySignature:String},setup(i){const x=i,O=z(()=>{const h=x.layoutSettings,e=(y,g)=>{const u=Number(h[`biometricDtrTemplate2${y}`]??g);return Number.isFinite(u)?Math.min(18,Math.max(1,u)):g};return{"--dtr-republic-size":`${e("RepublicFontSize",8)}pt`,"--dtr-department-size":`${e("DepartmentFontSize",8)}pt`,"--dtr-address-size":`${e("AddressFontSize",8)}pt`,"--dtr-title-size":`${e("TitleFontSize",13)}pt`,"--dtr-name-size":`${e("NameFontSize",10)}pt`,"--dtr-company-size":`${e("CompanyFontSize",8)}pt`,"--dtr-approved-size":`${e("ApprovedFontSize",10)}pt`,"--dtr-timekeeper-size":`${e("TimekeeperFontSize",10)}pt`,"--dtr-month-size":`${e("MonthFontSize",10)}pt`,"--dtr-table-content-size":`${e("TableContentFontSize",7)}pt`,"--dtr-table-header-size":`${e("TableHeaderFontSize",7)}pt`,"--dtr-employee-size":`${e("EmployeeFontSize",10)}pt`}}),v=z(()=>{const h=x.layoutSettings.biometricDtrTemplate2LogoPosition;return h==="center"?"top":["top","left","right","bottom"].includes(h)?h:"left"}),A=z(()=>{const h={Left:0,Right:4,Top:0,Bottom:3},e=g=>{const u=Number(x.layoutSettings[`biometricDtrTemplate2Logo${g}`]??h[g]);return Number.isFinite(u)?Math.max(0,Math.min(30,u)):h[g]},y=g=>{const u=Number(x.layoutSettings[`biometricDtrTemplate2Logo${g}`]??15);return`${Number.isFinite(u)?Math.max(1,Math.min(50,u)):15}mm`};return{translate:`${e("Right")-e("Left")}mm ${e("Bottom")-e("Top")}mm`,width:y("Width"),height:y("Height")}}),T=z(()=>{var h,e,y,g,u,b,F;return((e=(h=x.user)==null?void 0:h.department_ref)==null?void 0:e.department_name)||((g=(y=x.user)==null?void 0:y.departmentRef)==null?void 0:g.department_name)||(typeof((u=x.user)==null?void 0:u.department)=="string"?x.user.department:(F=(b=x.user)==null?void 0:b.department)==null?void 0:F.department_name)||""}),_=(h,e)=>{const y=e(h,"hrs"),g=e(h,"min");return y||g?`${y||"0"}:${String(g||"0").padStart(2,"0")}`:""};return(h,e)=>{var y,g;return c(),f("article",{class:H(["dtr-two",`dtr-two-logo-${v.value}`]),style:V(O.value)},[t("header",{class:H(["dtr-two-header",{"dtr-two-header-with-logo":i.companyLogo}])},[i.companyLogo?(c(),f("img",{key:0,src:i.companyLogo,style:V(A.value),alt:"Company logo"},null,12,mt)):k("",!0),e[0]||(e[0]=t("div",{class:"dtr-two-republic"},"Republic of the Philippines",-1)),t("strong",pt,p(i.companyName),1),T.value?(c(),f("strong",ut,p(T.value),1)):k("",!0),i.headerAddress?(c(),f("div",ct,p(i.headerAddress),1)):k("",!0)],2),e[17]||(e[17]=t("h1",null,"DAILY TIME RECORD",-1)),t("div",gt,[t("span",ft,[e[1]||(e[1]=S("Name: ",-1)),t("strong",null,p((y=i.user)==null?void 0:y.name),1)]),t("span",ht,[e[2]||(e[2]=S("For the Month of: ",-1)),t("strong",null,p(i.monthYear),1)])]),t("table",null,[t("colgroup",null,[e[3]||(e[3]=t("col",{style:{width:"6%"}},null,-1)),(c(),f(N,null,D(6,u=>t("col",{key:u,style:{width:"9%"}})),64)),e[4]||(e[4]=t("col",{style:{width:"8%"}},null,-1)),e[5]||(e[5]=t("col",{style:{width:"8%"}},null,-1)),e[6]||(e[6]=t("col",{style:{width:"24%"}},null,-1))]),t("thead",null,[e[7]||(e[7]=t("tr",null,[t("th",{rowspan:"2"},"DATE"),t("th",{colspan:"2"},"AM"),t("th",{colspan:"2"},"PM"),t("th",{colspan:"2"},[S("OVER"),t("wbr"),S("TIME")]),t("th",{rowspan:"2"},[S("UNDER"),t("br"),S("TIME")]),t("th",{rowspan:"2"},[S("OVER"),t("br"),S("TIME")]),t("th",{rowspan:"2"},"REMARKS")],-1)),t("tr",null,[(c(),f(N,null,D(["IN","OUT","IN","OUT","IN","OUT"],(u,b)=>t("th",{key:b},p(u),1)),64))])]),t("tbody",null,[(c(!0),f(N,null,D(i.days,u=>(c(),f("tr",{key:u},[t("td",null,p(u),1),(c(),f(N,null,D(["am_in","am_out","pm_in","pm_out"],b=>t("td",{key:b},p(i.recordValue(u,b)),1)),64)),e[8]||(e[8]=t("td",null,null,-1)),e[9]||(e[9]=t("td",null,null,-1)),t("td",null,p(_(u,i.undertimeValue)),1),e[10]||(e[10]=t("td",null,null,-1)),e[11]||(e[11]=t("td",null,null,-1))]))),128)),e[12]||(e[12]=t("tr",{class:"dtr-two-total"},[t("td",{colspan:"10"},"TOTAL")],-1))])]),e[18]||(e[18]=t("p",{class:"dtr-two-certification"},"I CERTIFY on my honor that the above statement is correct and that the services were applied to the public service.",-1)),t("p",yt,"Dated at "+p(T.value||i.companyName)+" on, __________________",1),t("div",bt,[t("strong",null,p((g=i.user)==null?void 0:g.name),1),e[13]||(e[13]=t("div",null,"Signature",-1))]),t("div",wt,[t("div",xt,[e[14]||(e[14]=t("p",null,[t("strong",null,"Approved:"),S(),t("em",null,"as certified and verified")],-1)),t("div",vt,[i.signatorySignature?(c(),f("img",{key:0,src:i.signatorySignature,alt:"Signatory e-signature"},null,8,St)):k("",!0),t("strong",null,p(i.signatoryName),1)])]),t("div",null,[e[15]||(e[15]=t("p",{class:"dtr-two-certification"},"I CERTIFY ON MY HONOR that the record of the hour of work was made daily at the time of arrival and departure from office and at beginning and ending of works.",-1)),t("div",zt,[i.timekeeperSignature?(c(),f("img",{key:0,src:i.timekeeperSignature,alt:"Time keeper e-signature"},null,8,kt)):k("",!0),t("strong",null,p(i.timekeeperName),1)]),e[16]||(e[16]=t("div",{class:"dtr-two-keeper"},"Time Keeper",-1))])])],6)}}},Tt=`.dtr-two { padding: 3mm; background: white; color: black; font-family: Arial, sans-serif; font-size: 8pt; }\r
.dtr-two-header { min-height: 12mm; position: relative; text-align: center; padding: 0 13mm; line-height: 1.3; }\r
.dtr-two-header img { position: absolute; left: 4mm; top: 0; width: 15mm; height: 15mm; object-fit: contain; }\r
.dtr-two-header strong { display: block; }\r
.dtr-two h1 { text-align: center; font-size: var(--dtr-title-size, 13pt); margin: 3mm 0; font-weight: bold; }\r
.dtr-two-info { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 3mm; margin: 4mm 0; font-size: 7pt; }\r
.dtr-two-info strong { border-bottom: 1px solid black; }\r
.dtr-two table { width: 100%; border-collapse: collapse; table-layout: fixed; }\r
.dtr-two th, .dtr-two td { border: 0.25mm solid black; text-align: center; padding: 0.35mm 0.1mm; font-size: 6pt; line-height: 1.15; overflow-wrap: anywhere; }\r
/* Keep all six time columns equal; rows expand with the chosen font size. */\r
.dtr-two th { font-weight: bold; overflow-wrap: normal; word-break: normal; vertical-align: middle; padding: 0.45em 0.2em; }\r
.dtr-two thead th[rowspan] { white-space: nowrap; }\r
.dtr-two td { padding: 0.2em 0.15em; }\r
.dtr-two tbody td:first-child { white-space: nowrap; }\r
.dtr-two tbody tr { height: 3.35mm; }\r
.dtr-two .dtr-two-total { height: auto; }\r
.dtr-two-total td { text-align: left; }\r
.dtr-two-certification, .dtr-two-dated { font-style: italic; font-size: 7pt; line-height: 1.2; margin: 1mm 0; }\r
.dtr-two-employee { width: 48%; margin: 3mm 0 1mm auto; text-align: center; font-size: 7pt; }\r
.dtr-two-employee strong { display: block; border-bottom: 1px solid black; }\r
.dtr-two-employee div { font-style: italic; }\r
.dtr-two-footer { display: grid; grid-template-columns: 42% 48%; justify-content: space-between; font-size: 7pt; }\r
.dtr-two-approved { display: flex; flex-direction: column; justify-content: flex-end; }\r
.dtr-two-signature { height: 10mm; border-bottom: 1px solid black; display: flex; align-items: center; justify-content: flex-end; flex-direction: column; position: relative; }\r
.dtr-two-signature img { position: absolute; bottom: 2mm; max-height: 9mm; max-width: 30mm; object-fit: contain; }\r
.dtr-two-signature strong { position: relative; }\r
.dtr-two-keeper-signature { height: 7mm; position: relative; display: flex; align-items: center; justify-content: flex-end; flex-direction: column; text-align: center; }\r
.dtr-two-keeper-signature img { position: absolute; bottom: 2mm; max-height: 9mm; max-width: 30mm; object-fit: contain; }\r
.dtr-two-keeper-signature strong { position: relative; }\r
.dtr-two-keeper { margin-top: 0; border-top: 1px solid black; text-align: center; font-style: italic; }\r
\r
/* Variables live on the article so the isolated print document keeps customization. */\r
.dtr-two .dtr-two-company { font-size: var(--dtr-company-size, 8pt); overflow-wrap: anywhere; }\r
.dtr-two .dtr-two-name { font-size: var(--dtr-name-size, 10pt); }\r
.dtr-two .dtr-two-approved { font-size: var(--dtr-approved-size, 10pt); }\r
.dtr-two .dtr-two-keeper-signature, .dtr-two .dtr-two-keeper { font-size: var(--dtr-timekeeper-size, 10pt); }\r
.dtr-two .dtr-two-employee strong { font-size: var(--dtr-employee-size, 10pt); }\r
.dtr-two-logo-right .dtr-two-header img { left: auto; right: 4mm; }\r
.dtr-two-logo-top .dtr-two-header-with-logo { padding-top: 13mm; }\r
.dtr-two-logo-top .dtr-two-header img { left: 50%; transform: translateX(-50%); }\r
\r
.dtr-two .dtr-two-month { font-size: var(--dtr-month-size, 10pt); }\r
.dtr-two thead th { font-size: var(--dtr-table-header-size, 7pt); font-weight: 700; }\r
\r
.dtr-two-logo-bottom .dtr-two-header-with-logo { padding-bottom: 13mm; }\r
.dtr-two-logo-bottom .dtr-two-header img { left: 50%; top: auto; bottom: 0; transform: translateX(-50%); }\r
\r
.dtr-two .dtr-two-republic { font-size: var(--dtr-republic-size, 8pt); overflow-wrap: anywhere; }\r
\r
.dtr-two .dtr-two-department { font-size: var(--dtr-department-size, 8pt); overflow-wrap: anywhere; }\r
\r
.dtr-two .dtr-two-address { font-size: var(--dtr-address-size, 8pt); overflow-wrap: anywhere; }\r
\r
.dtr-two tbody td { font-size: var(--dtr-table-content-size, 7pt); font-weight: 400; }\r
`,$t=`
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; background: white; color: black; }
  @page { size: A4 landscape; margin: 5mm; }
  .page-wrapper {
    display: flex;
    align-items: flex-start;
    width: 287mm;
    gap: 3mm;
    break-inside: avoid;
    page-break-inside: avoid;
  }
  .page-wrapper + .page-wrapper { break-before: page; page-break-before: always; }
  .form-copy { width: 142mm; flex: 0 0 142mm; break-inside: avoid; }
  ${Tt}
`,Mt={class:"space-y-4"},Dt={key:0,class:"flex flex-col md:flex-row gap-4 justify-between items-start md:items-center bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 print:hidden"},At={class:"flex items-center gap-3"},_t={class:"flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap"},Ct={class:"flex items-center gap-2"},Ft=["value"],Ot={style:{"margin-top":"10px","border-bottom":"2px solid #111827",position:"relative"}},Rt={key:0,style:{position:"absolute",top:"0",right:"0",display:"flex","padding-right":"10px","justify-content":"center","align-items":"center","margin-bottom":"4px"}},Et=["src"],It={style:{"text-align":"center","font-size":"6pt",color:"#4b5563",margin:"0",padding:"0"}},Lt={style:{"text-align":"center","font-size":"6pt",color:"#4b5563","margin-top":"8pt"}},Ut={style:{"text-align":"center","font-size":"12pt","font-weight":"700",color:"#111827","margin-top":"5px"}},Pt={style:{display:"grid","grid-template-columns":"1fr 1fr","column-gap":"16px","row-gap":"10px","margin-bottom":"16px","font-size":"14px"}},Yt={style:{"line-height":"8pt","grid-column":"span 2",display:"flex","align-items":"center","margin-top":"10px"}},jt={style:{"font-weight":"600","text-align":"center",color:"#111827",width:"100%","font-size":"8pt","border-bottom":"1px solid #111827"}},Vt={style:{overflow:"visible","margin-bottom":"20px"}},Ht={style:{width:"100%","table-layout":"fixed","border-collapse":"collapse",border:"1px solid #111827","font-size":"14px"}},Bt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center",color:"#111827"}},Wt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},qt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},Kt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},Xt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},Gt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},Jt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},Qt={style:{color:"#374151"}},Zt={style:{"margin-top":"32px"}},te={style:{"margin-left":"auto",width:"50%","text-align":"center"}},ee={key:0,style:{display:"flex","justify-content":"center","margin-bottom":"6px"}},re=["src"],ne={style:{"font-size":"7pt","text-transform":"uppercase"}},oe=`
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
`,ie={__name:"PrintableAttendance",props:{settingsOverride:{type:Object,default:null},user:Object,selectedYear:Number,selectedMonth:Number,attendanceRecords:Array,companyName:{type:String,default:"Biometric System"},companyLogo:{type:String,default:""},showLogo:{type:Boolean,default:!1},showControls:{type:Boolean,default:!0},signatoryName:{type:String,default:""},signatorySignature:{type:String,default:""},signatorySignatureEnabled:{type:Boolean,default:!1},calculateUndertime:{type:Boolean,default:void 0},overrides:{type:Array,default:()=>[]}},setup(i,{expose:x}){const O=lt(),v=z(()=>e.settingsOverride??O),A=z(()=>v.value.biometricDtrTemplate==="template2"),T=L(null),_=L(4),h=L(!1),e=i,y=z(()=>typeof e.calculateUndertime=="boolean"?e.calculateUndertime:h.value),g=z(()=>!e.selectedMonth||!e.selectedYear?"N/A":new Date(e.selectedYear,e.selectedMonth-1).toLocaleDateString("en-US",{month:"long",year:"numeric"})),u=z(()=>{if(!e.selectedMonth||!e.selectedYear)return[];const n=new Date(e.selectedYear,e.selectedMonth,0).getDate();return Array.from({length:n},(r,s)=>s+1)}),b=n=>{if(!n)return null;if(n instanceof Date){const o=n.getTime();return Number.isNaN(o)?null:n.getHours()*60+n.getMinutes()}const r=String(n).trim();if(!r)return null;const s=r.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*([AaPp][Mm])?$/);if(s){let o=Number(s[1]);const m=Number(s[2]),w=(s[3]||"").toUpperCase();return Number.isNaN(o)||Number.isNaN(m)||(w==="AM"?o===12&&(o=0):w==="PM"&&o<12&&(o+=12),o<0||o>23||m<0||m>59)?null:o*60+m}const l=r.split(":");if(l.length<2)return null;const d=Number(l[0]),a=Number(l[1]);return Number.isNaN(d)||Number.isNaN(a)?null:d*60+a},F=n=>{if(!Number.isFinite(n)||n<=0)return"";const r=Math.floor(n/60),s=n%60;return{hours:r,minutes:s}},W=()=>{var d,a;const n=((d=e.user)==null?void 0:d.office_shift)||((a=e.user)==null?void 0:a.officeShift),s=(Array.isArray(n==null?void 0:n.schedules)?[...n.schedules]:[]).sort((o,m)=>(o.sequence||0)-(m.sequence||0));if(!s.length)return null;const l=s.reduce((o,m)=>{const w=b(m==null?void 0:m.time_in),M=b(m==null?void 0:m.time_out);return w===null||M===null||M<=w?o:o+(M-w)},0);return l>0?l:null},q=n=>{const r=b(n==null?void 0:n.am_in),s=b(n==null?void 0:n.am_out),l=b(n==null?void 0:n.pm_in),d=b(n==null?void 0:n.pm_out);let a=0;return r!==null&&s!==null&&s>r&&(a+=s-r),l!==null&&d!==null&&d>l&&(a+=d-l),a>0?a:null},K=n=>{var d,a;const r=String(n).padStart(2,"0"),s=String(e.selectedMonth).padStart(2,"0"),l=`${e.selectedYear}-${s}-${r}`;return((a=(d=e.attendanceRecords)==null?void 0:d.find)==null?void 0:a.call(d,o=>o.date===l))||null},X=n=>{const r=new Date(n);return Number.isNaN(r.getTime())?"":r.toLocaleTimeString("en-US",{hour:"2-digit",minute:"2-digit",hour12:!0})},G=n=>{const r=new Date(n);if(Number.isNaN(r.getTime()))return null;const s=r.getFullYear(),l=String(r.getMonth()+1).padStart(2,"0"),d=String(r.getDate()).padStart(2,"0");return`${s}-${l}-${d}`},J=n=>{const r=String((n==null?void 0:n.new_checktype)||"").trim().toUpperCase(),s=new Date(n==null?void 0:n.new_checktime);if(Number.isNaN(s.getTime()))return null;const l=s.getHours();return r==="I"?l<12?"am_in":"pm_in":r==="O"?l<=12?"am_out":"pm_out":null},U=(n,r)=>{if(!Array.isArray(e.overrides)||!e.overrides.length)return"";const s=String(n).padStart(2,"0"),l=String(e.selectedMonth).padStart(2,"0"),d=`${e.selectedYear}-${l}-${s}`,a=e.overrides.filter(o=>G(o==null?void 0:o.new_checktime)===d).filter(o=>J(o)===r).sort((o,m)=>{const w=new Date((o==null?void 0:o.updated_at)||(o==null?void 0:o.created_at)||(o==null?void 0:o.new_checktime)).getTime();return new Date((m==null?void 0:m.updated_at)||(m==null?void 0:m.created_at)||(m==null?void 0:m.new_checktime)).getTime()-w});return a.length?X(a[0].new_checktime):""},Q=n=>{const r=K(n);if(!r)return null;const s=["am_in","am_out","pm_in","pm_out"],l={...r};return s.forEach(d=>{const a=U(n,d);a&&(l[d]=a)}),l},$=(n,r)=>{if(!e.attendanceRecords||!Array.isArray(e.attendanceRecords))return"";if(r==="am_in"||r==="am_out"||r==="pm_in"||r==="pm_out"){const o=U(n,r);if(o)return o}const s=String(n).padStart(2,"0"),l=String(e.selectedMonth).padStart(2,"0"),d=`${e.selectedYear}-${l}-${s}`,a=e.attendanceRecords.find(o=>o.date===d);if(!a)return"";switch(r){case"am_in":return a.am_in||"";case"am_out":return a.am_out||"";case"pm_in":return a.pm_in||"";case"pm_out":return a.pm_out||"";case"undertime_hrs":return a.undertimeHrs||"";case"undertime_min":return a.undertimeMin||"";default:return""}},R=(n,r)=>{const s=()=>{const w=$(n,"undertime_hrs"),M=$(n,"undertime_min"),C=String(w||"").trim(),E=String(M||"").trim(),I=Number(C),Y=Number(E),j=C!==""&&!Number.isNaN(I)&&I>0,rt=E!==""&&!Number.isNaN(Y)&&Y>0;return!j&&!rt?"":r==="hrs"?j?String(I):"":E};if(!y.value)return s();const l=Q(n);if(!l)return"";const d=W(),a=q(l);if(d===null||a===null)return s();const o=Math.max(0,d-a);if(o<=0)return"";const m=F(o);return m?r==="hrs"?String(m.hours):String(m.minutes).padStart(2,"0"):""},Z=()=>{var n;return((n=T.value)==null?void 0:n.innerHTML)||""},tt=(n,r)=>{const s=r||1,l=A.value?2:4;let d="";for(let a=0;a<s;a+=l){const o=[];for(let C=0;C<l;C++)a+C<s&&o.push(`<div class="form-copy">${n}</div>`);const m=o.join(""),w=a+l>=s;d+=`<div class="page-wrapper">${m}</div>`}return d},P=(n=_.value||1)=>{var s;const r=(s=T.value)==null?void 0:s.innerHTML;return r?{bodyHtml:tt(r,n),styles:A.value?$t:oe,formsPerPage:A.value?2:4}:null},et=()=>{const n=P(_.value||1);if(!n)return;const r=window.open("","_blank");if(!r){window.alert("Allow pop-ups to open the print preview.");return}r.document.write(`
    <!DOCTYPE html>
    <html>
      <head>
        <meta charset="UTF-8" />
        <title>Daily Time Record</title>
        <style>
                    ${n.styles}
        </style>
      </head>
      <body>
                ${n.bodyHtml}
      </body>
    </html>
  `),r.document.close(),at(r,{closeAfterPrint:!0})};return x({getPrintPayload:P,getPrintContent:Z}),(n,r)=>{var s,l,d,a;return c(),f("div",Mt,[i.showControls?(c(),f("div",Dt,[r[4]||(r[4]=t("div",{class:"flex flex-col gap-2"},[t("h3",{class:"text-lg font-semibold text-gray-900 dark:text-white"},"Printable Daily Time Record"),t("p",{class:"text-sm text-gray-600 dark:text-gray-400"},"Print official attendance record for payroll")],-1)),t("div",At,[t("label",_t,[B(t("input",{"onUpdate:modelValue":r[0]||(r[0]=o=>h.value=o),type:"checkbox",class:"h-4 w-4"},null,512),[[nt,h.value]]),r[2]||(r[2]=S(" Calculate Undertime ",-1))]),t("div",Ct,[r[3]||(r[3]=t("label",{class:"text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap"},"Copies:",-1)),B(t("select",{"onUpdate:modelValue":r[1]||(r[1]=o=>_.value=o),class:"h-9 px-3 rounded border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-200 font-medium"},[(c(),f(N,null,D(10,o=>t("option",{key:o,value:o},p(o),9,Ft)),64))],512),[[ot,_.value,void 0,{number:!0}]])]),t("button",{onClick:et,class:"px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors flex items-center gap-2"}," Print Record ")])])):k("",!0),t("div",{ref_key:"printContainer",ref:T,style:{background:"white",padding:"0px",border:"1px solid #d1d5db","border-radius":"8px",color:"#111827"}},[A.value?(c(),it(Nt,{key:0,user:i.user,"company-name":i.companyName,"company-logo":i.showLogo?i.companyLogo:"","header-address":v.value.biometricDtrHeaderAddress,"layout-settings":v.value,"month-year":g.value,days:u.value,"record-value":$,"undertime-value":R,"timekeeper-name":v.value.biometricDtrTimekeeperUseDefault?v.value.biometricDtrTimekeeperName:"","timekeeper-signature":v.value.biometricDtrTimekeeperUseDefault&&v.value.biometricDtrTimekeeperSignatureEnabled?v.value.biometricDtrTimekeeperSignature:"","signatory-name":i.signatoryName,"signatory-signature":i.signatorySignatureEnabled?i.signatorySignature:""},null,8,["user","company-name","company-logo","header-address","layout-settings","month-year","days","timekeeper-name","timekeeper-signature","signatory-name","signatory-signature"])):(c(),f(N,{key:1},[t("div",Ot,[i.showLogo&&i.companyLogo?(c(),f("div",Rt,[t("img",{src:i.companyLogo,alt:"Company logo",style:{"max-height":"50px","object-fit":"contain"}},null,8,Et)])):k("",!0),r[5]||(r[5]=t("p",{style:{"font-size":"8px","font-weight":"600",color:"#374151","margin-bottom":"4px","padding-left":"10px"}},"CSC Form No. 48",-1)),r[6]||(r[6]=t("h1",{style:{"text-align":"center","font-size":"8pt","font-weight":"700",color:"#111827",margin:"0",padding:"0"}}," DAILY TIME RECORD",-1)),t("p",It,p(i.companyName||"Company / School Name"),1),t("p",Lt,p(((s=i.user)==null?void 0:s.department)||((d=(l=i.user)==null?void 0:l.department_ref)==null?void 0:d.department_name)||""),1),t("h1",Ut,p((a=i.user)==null?void 0:a.name),1)]),t("div",Pt,[t("div",Yt,[r[7]||(r[7]=t("p",{style:{color:"#4b5563","font-size":"7.5pt","white-space":"nowrap","padding-right":"5px"}},"For the Month of",-1)),t("p",jt,p(g.value),1)]),r[8]||(r[8]=st('<div style="line-height:2pt;display:flex;align-items:center;" data-v-33cb8d6e><p style="color:#4b5563;font-size:7.5pt;" data-v-33cb8d6e>Official Hours</p><p style="font-weight:600;color:#111827;" data-v-33cb8d6e></p></div><div style="line-height:8pt;display:flex;align-items:center;" data-v-33cb8d6e><span style="color:#4b5563;font-size:7.5pt;white-space:nowrap;padding-right:5px;" data-v-33cb8d6e> Regular Days </span><span style="flex:1;font-weight:600;color:#111827;border-bottom:1px solid #111827;" data-v-33cb8d6e></span></div><div style="line-height:8pt;display:flex;align-items:center;" data-v-33cb8d6e><p style="color:#4b5563;font-size:7.5pt;" data-v-33cb8d6e>Arrival and Departure</p><p style="font-weight:600;color:#111827;border-bottom:1px solid #111827;" data-v-33cb8d6e></p></div><div style="line-height:8pt;display:flex;align-items:center;" data-v-33cb8d6e><p style="color:#4b5563;font-size:7.5pt;padding-right:10px;" data-v-33cb8d6e>Saturdays</p><p style="width:100%;font-weight:600;color:#111827;border-bottom:1px solid #111827;" data-v-33cb8d6e></p></div>',4))]),t("div",Vt,[t("table",Ht,[t("colgroup",null,[r[9]||(r[9]=t("col",{style:{width:"10%"}},null,-1)),(c(),f(N,null,D(6,o=>t("col",{key:o,style:{width:"15%"}})),64))]),r[11]||(r[11]=t("thead",null,[t("tr",{style:{background:"#f3f4f6"}},[t("th",{rowspan:"2",style:{"font-size":"8.5pt",border:"1px solid #111827",padding:"8px 0","text-align":"center","vertical-align":"middle","white-space":"nowrap","font-weight":"700",color:"#111827"}}," DAY "),t("th",{colspan:"2",style:{"font-size":"8.5pt",border:"1px solid #111827",padding:"8px","text-align":"center","font-weight":"700",color:"#111827"}}," A.M. "),t("th",{colspan:"2",style:{"font-size":"8.5pt",border:"1px solid #111827",padding:"8px","text-align":"center","font-weight":"700",color:"#111827"}}," P.M. "),t("th",{colspan:"2",style:{"font-size":"8.5pt",border:"1px solid #111827",padding:"8px","text-align":"center","font-weight":"700",color:"#111827"}}," UNDERTIME ")]),t("tr",{style:{background:"#f3f4f6"}},[t("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," IN"),t("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," OUT"),t("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," IN"),t("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," OUT"),t("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," Hrs."),t("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," Min.")])],-1)),t("tbody",null,[(c(!0),f(N,null,D(u.value,o=>(c(),f("tr",{key:o},[t("td",Bt,p(o),1),t("td",Wt,p($(o,"am_in")),1),t("td",qt,p($(o,"am_out")),1),t("td",Kt,p($(o,"pm_in")),1),t("td",Xt,p($(o,"pm_out")),1),t("td",Gt,p(R(o,"hrs")),1),t("td",Jt,p(R(o,"min")),1)]))),128)),r[10]||(r[10]=t("tr",{style:{background:"#f3f4f6","font-weight":"700"}},[t("td",{colspan:"7",style:{border:"1px solid #111827",padding:"8px","text-align":"left",color:"#111827"}}," TOTAL ")],-1))])])]),t("div",Qt,[r[16]||(r[16]=t("p",{style:{"font-style":"italic","margin-bottom":"16px","font-size":"8pt"}},"     I CERTIFY on my honor that the above is a true and correct report of the hours of work performed, a record of which was made daily at the time of arrival at and departure from office. ",-1)),t("div",Zt,[r[14]||(r[14]=t("div",{style:{"margin-left":"auto",width:"50%","text-align":"center"}},[t("div",{style:{"border-top":"1px solid #111827"}})],-1)),r[15]||(r[15]=t("div",{style:{}},[t("p",{style:{"font-style":"italic","margin-bottom":"16px","font-size":"8pt"}},"     Verified as to the prescribed office hours. ")],-1)),t("div",te,[i.signatorySignatureEnabled&&i.signatorySignature?(c(),f("div",ee,[t("img",{src:i.signatorySignature,alt:"Signatory e-signature",style:{"max-height":"40px","max-width":"160px","object-fit":"contain"}},null,8,re)])):k("",!0),t("p",ne,p(i.signatoryName),1),r[12]||(r[12]=t("div",{style:{"border-top":"1px solid #111827"}},null,-1)),r[13]||(r[13]=t("p",{style:{color:"#111827","margin-top":"0px","font-style":"italic","font-size":"8pt"}},"In-Charge",-1))])])])],64))],512)])}}},me=dt(ie,[["__scopeId","data-v-33cb8d6e"]]);export{me as P};
