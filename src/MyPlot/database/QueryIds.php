<?php
declare(strict_types=1);

namespace MyPlot\database;

interface QueryIds
{
    public const INIT_PLOTS_V2 = "myplot.init.plotsV2";
    public const INIT_MERGED_PLOTS_V2 = "myplot.init.mergedPlotsV2";

    public const GET_PLOT = "myplot.get_plot";
    public const GET_PLOTS_BY_OWNER = "myplot.get_plots_by_owner";
    public const GET_PLOTS_BY_OWNER_AND_LEVEL = "myplot.get_plots_by_owner_and_level";
    public const GET_EXISTING_XZ = "myplot.get_existing_XZ";
    public const GET_MERGE_ORIGIN = "myplot.get_merge_origin";
    public const GET_MERGED_PLOTS = "myplot.get_merged_plots";

    public const SAVE_PLOT = "myplot.save_plot";
    public const REMOVE_PLOT = "myplot.remove_plot";
    public const DISPOSE_MERGED_PLOT = "myplot.dispose_merged_plot";
    public const MERGE_PLOT = "myplot.merge_plot";
}