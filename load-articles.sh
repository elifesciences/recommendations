#!/bin/bash
set -e

if [ "$#" -ne "1" ]; then
    echo "Usage: $0 ARTICLE_IDS_FILE"
    echo "Example: $0 article_ids.txt"
    echo "Each id should be on a separate line"
    exit 1
fi

xargs -I {} ./load-article.sh {} < "$1"
