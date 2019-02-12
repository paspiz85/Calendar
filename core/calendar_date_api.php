<?php

# Copyright (c) 2018 Grigoriy Ermolaev (igflocal@gmail.com)
# Calendar for MantisBT is free software: 
# you can redistribute it and/or modify it under the terms of the GNU
# General Public License as published by the Free Software Foundation, 
# either version 2 of the License, or (at your option) any later version.
#
# Calendar plugin for for MantisBT is distributed in the hope 
# that it will be useful, but WITHOUT ANY WARRANTY; without even the 
# implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
# See the GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Customer management plugin for MantisBT.  
# If not, see <http://www.gnu.org/licenses/>.

function days_of_number_week( $p_start_step_days, $p_count_step_days, $t_week_days_name, $p_week = null ) {

    if( $p_week == null ) {
        $t_week = date( "e" );
    }

    $t_week         = $p_week - 1;
    $t_curent_years = date( "Y" );
    $t_days_week    = array();

    for( $i = $p_start_step_days; $i < $p_start_step_days + $p_count_step_days; $i++ ) {

        $t_day_cheks = date( "D", strtotime( 'last monday + ' . $i . ' days 1/1/' . $t_curent_years . ' + ' . $t_week . ' weeks' ) );

        if( $t_week_days_name[$t_day_cheks] == ON ) {
            $t_days_week[$i] = date( strtotime( 'last monday + ' . $i . ' days 1/1/' . $t_curent_years . ' + ' . $t_week . ' weeks' ) );
        }
    }
    return $t_days_week;
}

function times_day( $p_date, $p_full_time = FALSE ) {

    if( $p_full_time == TRUE ) {
        $t_time_day_start  = 0;
        $t_time_day_finish = 86400;
    } else {
        $t_time_day_start  = plugin_config_get( 'time_day_start', plugin_config_get( 'time_day_start' ), FALSE, auth_get_current_user_id() );
        $t_time_day_finish = plugin_config_get( 'time_day_finish', plugin_config_get( 'time_day_finish' ), FALSE, auth_get_current_user_id() );
    }

    $t_time_start  = $p_date + $t_time_day_start;
    $t_time_finish = $p_date + $t_time_day_finish;
    $t_time_count  = 3600 / plugin_config_get( 'stepDayMinutesCount' );
    $t_dates       = range( $t_time_start, $t_time_finish, $t_time_count );

    return $t_dates;
}

function get_dates_event_from_events_id( $p_events_id ) {

    $t_dates = array();

    foreach( $p_events_id as $t_event_id ) {
        
        if( !event_exists( $t_event_id ) || !access_has_event_level( plugin_config_get( 'view_event_threshold', NULL, FALSE, NULL, event_get_field( $t_event_id, "project_id" ) ), $t_event_id )) {
            continue;
        }

        if( event_get_field( $t_event_id, 'recurrence_pattern' ) != '' ) {
            $t_rset          = new RRule\RSet( event_get_field( $t_event_id, 'recurrence_pattern' ) );
            $t_previous_days = $t_rset->getOccurrencesBetween( (int) event_get_field( $t_event_id, 'date_from' ), strtotime( 'tomorrow' ) );
            $t_next_days     = $t_rset->getOccurrencesBetween( strtotime( 'tomorrow' ), (int) event_get_field( $t_event_id, 'date_to' ), 1 );
            foreach( $t_previous_days as $t_previous_day ) {
                $t_time_start_day                                              = strtotime( date( "j.n.Y", $t_previous_day->getTimestamp() ) );
                $t_dates[$t_time_start_day][$t_previous_day->getTimestamp()][] = $t_event_id;
            }
            foreach( $t_next_days as $t_next_day ) {
                $t_time_start_day                                          = strtotime( date( "j.n.Y", $t_next_day->getTimestamp() ) );
                $t_dates[$t_time_start_day][$t_next_day->getTimestamp()][] = $t_event_id;
            }
        } else {
            $t_time_start_day                                                          = strtotime( date( "j.n.Y", event_get_field( $t_event_id, "date_from" ) ) );
            $t_dates[$t_time_start_day][event_get_field( $t_event_id, "date_from" )][] = $t_event_id;
        }
    }
    ksort( $t_dates );
    return $t_dates;
}

function group_events_by_time( $p_events_id ) {
    if( $p_events_id == false || $p_events_id == 0 )
        return 0;

    $arEventsTemp = array();

    foreach( $p_events_id as $t_event_id ) {

        $arEventsTemp [event_get_field( $t_event_id, "date_from" )][] = $t_event_id;
    }

    krsort( $arEventsTemp );

    return $arEventsTemp;
}

function get_events_id_inside_days( $p_ar_all_days, $p_project_id, $p_user_id = ALL_USERS ) {

    $t_table_calendar_events = plugin_table( 'events' );
    $t_table_calendar_members = plugin_table( 'event_member' );

    $t_project_all = project_hierarchy_get_all_subprojects( $p_project_id );
    $t_project_all = array_merge($t_project_all, array($p_project_id));

    if( db_table_exists( $t_table_calendar_events ) && db_table_exists( $t_table_calendar_members ) && db_is_connected() ) {

        $t_days = array();
        db_param_push();

        if( $p_user_id == ALL_USERS ) {
            $p_query = "SELECT id,date_from,recurrence_pattern FROM " . $t_table_calendar_events .
                    " WHERE "
                    . "activity = 'Y' AND project_id IN (" . implode( ',', $t_project_all ) . ") AND date_from BETWEEN " . db_param() . " AND " . db_param() . " "
                    . "OR "
                    . "( activity = 'Y' AND project_id IN (" . implode( ',', $t_project_all ) . ") AND date_from < " . db_param() . " AND date_to > " . db_param() . " AND recurrence_pattern > '' )";
        } else {
            $p_query = "SELECT id,project_id,date_from,recurrence_pattern FROM " . $t_table_calendar_events . " AS et" . 
                    " INNER JOIN " . $t_table_calendar_members . " AS mt" .
                    " ON et.id = mt.event_id" .
                    " WHERE "
                    . "activity = 'Y' AND project_id IN (" . implode( ',', $t_project_all ) . ") AND date_from BETWEEN " . db_param() . " AND " . db_param() . " AND mt.user_id = " . db_param() . " "
                    . "OR "
                    . "( activity = 'Y' AND project_id IN (" . implode( ',', $t_project_all ) . ") AND date_from < " . db_param() . " AND date_to > " . db_param() . " AND recurrence_pattern > '' AND mt.user_id = " . db_param() . " )";
        }


        foreach( $p_ar_all_days as $t_day ) {

            $t_time_start_day  = (int) $t_day;
            $t_time_finish_day = $t_day + 86399;

            if( $p_user_id == ALL_USERS ) {
                $t_result = db_query( $p_query, array( $t_time_start_day, $t_time_finish_day, $t_time_finish_day, $t_time_start_day ) );
            } else {
                $t_result = db_query( $p_query, array( $t_time_start_day, $t_time_finish_day, $p_user_id, $t_time_finish_day, $t_time_start_day, $p_user_id ) );
            }
            $t_event_count = db_num_rows( $t_result );
            if( $t_event_count > 0 ) {
                $t_days[$t_day] = [];
                for( $i = 0; $i < $t_event_count; $i++ ) {
                    $t_row = db_fetch_array( $t_result );

                    $t_access_show_current_user = access_has_event_level( plugin_config_get( 'view_event_threshold' ), (int)$t_row["id"] );
//
                    if( $t_access_show_current_user == TRUE ) {

                        $t_time_event_start = (int)$t_row['date_from'];
                        $t_rrule_raw        = $t_row['recurrence_pattern'];
                        if( $t_time_event_start < $t_day || $t_rrule_raw != NULL ) {
//                            $t_rrule_raw       = event_get_field( $t_row['id'], 'recurrence_pattern' );
                            $t_recurrenci_rule = RRule\RRule::createFromRfcString( $t_rrule_raw );
                            $t_is              = $t_recurrenci_rule->getOccurrencesBetween( $t_time_start_day, $t_time_finish_day );
                            if( $t_is != NULL ) {
                                $t_days[$t_day][date_timestamp_get( $t_is[0] )][] = (int)$t_row["id"];
                            }
                        } else {
                            $t_days[$t_day][$t_time_event_start][] = (int)$t_row["id"];
                        }
                    }
                }
            } else {
                $t_days[$t_day] = [];
            }
        }
//        foreach( $arDays as $arDay => $keys ) {
//            if( count( $keys ) == 0 ) {
//                $t_events_in_days[$arDay] = [];
//            } else {
//                ksort( $keys );
//                $t_events_in_days[$arDay] = $keys;
//            }
//        }
//        return $t_events_in_days;
//        foreach( $arDays as $arDay => $keys ) {
//            if( count( $keys ) == 0 ) {
//                $t_events_in_days[$arDay] = [];
//            }
//            ksort( $keys );
//            foreach( $keys as $key ) {
//                foreach( $key as $k ) {
//                    $t_events_in_days[$arDay][] = $k;
//                }
//            }
//        }
//        return $t_events_in_days;
//        
//        foreach( $arDays as $arDay => $keys ) {
//            if( count( $keys ) == 0 ) {
//                $t_events_in_days[$arDay] = [];
//            }
//            ksort( $keys );
//            foreach( $keys as $key ) {
//                foreach( $key as $k ) {
//                    $t_events_in_days[$arDay][] = $k;
//                }
//            }
//        }
//        return $t_events_in_days;
        return $t_days;
    }
    return false;
}
