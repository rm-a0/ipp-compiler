#!/bin/bash
# Directory containing the student folder files
STUDENT_DIR="."

# Output ZIP file name
ZIP_FILE="xrepcim00.zip"

# List of files to include in the ZIP
FILES=(
    "Environment.php"
    "Interpreter.php"
    "SOLBlock.php"
    "SOLBlockExpression.php"
    "SOLClass.php"
    "SOLExpression.php"
    "SOLLiteral.php"
    "SOLMethod.php"
    "SOLObject.php"
    "SOLSend.php"
    "SOLStatement.php"
    "SOLVariable.php"
    "XMLParser.php"
    "readme2.md"
    "class-diagram-detailed.png"
    "class-diagram-general.png"
    "flow-diagram.png"
)

# Optional rozsireni file
OPTIONAL_FILES=("rozsireni")

# Create the ZIP archive
cd "$STUDENT_DIR" || exit 1

# Add required files, checking for existence
for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        zip "$ZIP_FILE" "$file"
    else
        echo "Warning: $file not found and will be skipped."
    fi
done

# Add optional files if they exist
for file in "${OPTIONAL_FILES[@]}"; do
    if [ -f "$file" ]; then
        zip "$ZIP_FILE" "$file"
    fi
done

echo "ZIP archive created: $ZIP_FILE"