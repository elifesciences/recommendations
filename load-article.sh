#!/bin/bash
set -e

if [ "$#" -ne "1" ]; then
    echo "Usage: $0 ID"
    echo "Example: $0 10627"
    exit 1
fi

url=http://localhost/recommendations/article/$1
code_and_time=$(curl "$url" -w '%{http_code},%{time_total}' -o /dev/null)
echo "$url,$code_and_time"
#number=$(curl "$url" | jq .total)
#echo $url,$number
