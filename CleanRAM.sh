#!/bin/bash

# Get used memory before cleanup (in MB)
mem_before=$(free -m | awk '/^Mem:/ {print $3}')

# Ask user to proceed
zenity --question --text="Do you want to clear memory cache?" --title="Clean RAM"
if [ $? = 0 ]; then

    # Show animated progress bar
    (
    echo "10" ; sleep 0.3
    echo "# Syncing..." ; sleep 0.5
    echo "40" ; sleep 0.3
    echo "# Dropping caches..." ; sleep 0.7
    sync; echo 3 | sudo tee /proc/sys/vm/drop_caches > /dev/null
    echo "90" ; sleep 0.3
    echo "# Finalizing..." ; sleep 0.5
    echo "100"
    ) | zenity --progress --title="Cleaning RAM..." --auto-close --width=300

    # Get used memory after cleanup (in MB)
    mem_after=$(free -m | awk '/^Mem:/ {print $3}')
    mem_freed=$((mem_before - mem_after))

    # Display result
    zenity --info \
        --title="RAM Cleaned" \
        --text="✅ Memory cleaned!\n\n🧠 Before: ${mem_before} MB\n🧠 After: ${mem_after} MB\n🚀 Freed: ${mem_freed} MB"

fi

