export const customSources = {text:'Custom text', school_name:'School name', hall_room:'Hall / room', seat_no:'Seat number', exam_title:'Exam title', admission_no:'Admission number', academic_year:'Academic year'};
export function customFieldValue(field,sample){if(field.source==='text')return field.text||'';return `${field.label?field.label+': ':''}${sample[field.source]||''}`;}
export function overlayPositions(design){return {...design.field_positions,...Object.fromEntries((design.custom_fields||[]).map(field=>[field.id,field]))};}
