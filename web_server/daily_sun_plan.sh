#!/bin/bash

LOGFILE="/var/lib/rt2/sun_planner.log"
WEB_URL="http://localhost/rt2"

DATE=$(date +%Y-%m-%d)

STATUS="success"
MESSAGE=""

# process.php to get visibility
RESPONSE=$(curl -s -X POST -d "date=$DATE&targets[0][name]=Sun&targets[0][type]=solar&targets[0][enabled]=on" $WEB_URL/process.php)

# Check for errors
ERROR=$(echo $RESPONSE | jq -r '.error // empty')
if [ -n "$ERROR" ]; then
    STATUS="failed"
    MESSAGE="Error from process.php: $ERROR"
else
    # first window for Sun
    START=$(echo $RESPONSE | jq -r '.targets[0].windows[0].start // empty')
    END=$(echo $RESPONSE | jq -r '.targets[0].windows[0].end // empty')

    if [ -z "$START" ] || [ -z "$END" ]; then
        MESSAGE="No visibility window found for Sun on $DATE"
    else
        # convert to unix timestamps
        START_UNIX=$(date -d "$DATE $START" +%s 2>/dev/null)
        END_UNIX=$(date -d "$DATE $END" +%s 2>/dev/null)

        if [ -z "$START_UNIX" ] || [ -z "$END_UNIX" ]; then
            STATUS="failed"
            MESSAGE="Failed to parse times: $START - $END"
        else
            # plan_to_db.php
            JSON_PAYLOAD='{"object_name":"Sun","location":"solar","rec_start_time":'$START_UNIX',"end_time":'$END_UNIX'}'
            RESULT=$(curl -s -X POST -H "Content-Type: application/json" -d "$JSON_PAYLOAD" $WEB_URL/plan_to_db.php)

            PLAN_SUCCESS=$(echo $RESULT | jq -r '.success // false')
            if [ "$PLAN_SUCCESS" = "true" ]; then
                MESSAGE="Sun successfully planned for $DATE"
            else
                ERROR_MSG=$(echo $RESULT | jq -r '.error // "Unknown error"')
                MESSAGE="Sun not planned for $DATE: $ERROR_MSG"
            fi
        fi
    fi
fi

echo "$(date): [$STATUS] - $MESSAGE" >> $LOGFILE