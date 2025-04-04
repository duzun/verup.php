#!/bin/bash

# Create logs directory if it doesn't exist
# Get the script's directory, even when sourced
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" > /dev/null && pwd )"
LOGDIR="$SCRIPT_DIR/tmp/logs"
mkdir -p "$LOGDIR"

# Generate unique log file name with timestamp
LOGFILE="$LOGDIR/debug-$(basename "$SHELL").log"

echo > "$LOGFILE"

# Save original terminal settings
exec 3>&1 4>&2
ORIGINAL_PS1=$PS1

# Set up logging for all commands and their output
PS1='$(echo -e "\n[$(date +"%Y-%m-%d %H:%M:%S")] Command: $BASH_COMMAND" >> "$LOGFILE")'"$PS1"
exec 1> >(tee -a "$LOGFILE")
exec 2> >(tee -a "$LOGFILE" >&2)

# Set up trap to log exit
trap 'echo -e "\n[$(date +"%Y-%m-%d %H:%M:%S")] Session ended.\n" >> "$LOGFILE"; PS1="$ORIGINAL_PS1"' EXIT

# Print initial session info
echo -e "\n[$(date +"%Y-%m-%d %H:%M:%S")] Debug session started. Logging to: $LOGFILE\n" >> "$LOGFILE"

# Export the log file path so child processes can access it
export DEBUG_LOG_FILE="$LOGFILE"
