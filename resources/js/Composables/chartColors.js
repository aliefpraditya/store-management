/**
 * The colours the report charts give out, in this order and never cycled: the first five slots of the validated categorical
 * palette (blue, orange, aqua, yellow, magenta), whose neighbours stay apart for the colour-blind. The aqua, yellow and magenta
 * are pale on white, so a chart that uses them always prints the names and figures beside its marks.
 */
export const SERIES_COLORS = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4'];

/** What is folded into "Lainnya": a neutral that stands back from the series colours. */
export const OTHER_COLOR = '#c3c2b7';
