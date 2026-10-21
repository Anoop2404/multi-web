export function printGeometry(design) {
    const landscape = (design.page_orientation || 'landscape') === 'landscape';
    const availableWidth = landscape ? 277 : 190, availableHeight = landscape ? 190 : 277;
    const width = Math.min(availableWidth, Math.max(20, Number(design.card_width_mm) || 90));
    const height = Math.min(availableHeight, Math.max(20, Number(design.card_height_mm) || 121.384615));
    const gap = Math.max(0, Math.min(15, Number(design.card_gap_mm ?? 3)));
    const columns = Math.max(1, Math.floor((availableWidth + gap + .00001) / (width + gap)));
    const rows = Math.max(1, Math.floor((availableHeight + gap + .00001) / (height + gap)));
    return { width, height, gap, columns, rows, perPage: columns * rows, pageWidth:landscape ? 297 : 210, pageHeight:landscape ? 210 : 297 };
}
export function artworkBox(design) {
    const {width,height:dieHeight}=printGeometry(design);
    const height=dieHeight-remarksHeight(design);
    if(design.background_fit==='stretch')return {x:0,y:0,width,height};
    const aspect=design.layout==='stse_three_up'?780/1052:Number(design.background_aspect_ratio)||780/1052;
    const artWidth=Math.min(width,height*aspect),artHeight=artWidth/aspect;
    return {x:(width-artWidth)/2,y:(height-artHeight)/2,width:artWidth,height:artHeight};
}

export function remarksHeight(design){
    if(!String(design.remarks_text || '').trim())return 0;
    return Math.min(Math.max(15,Number(design.remarks_height_mm)||35),printGeometry(design).height-20);
}
