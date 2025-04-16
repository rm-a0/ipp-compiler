#!/bin/bash
# Directory containing the student folder files
STUDENT_DIR="."

# Output ZIP file name (use your login or a suitable name)
ZIP_FILE="xrepcim00.zip"

# Create the ZIP archive with all files in the student directory
cd "$STUDENT_DIR" || exit 1
zip "$ZIP_FILE" Environment.php Interpreter.php SOLBlock.php SOLBlockExpression.php SOLClass.php SOLExpression.php SOLLiteral.php SOLMethod.php SOLObject.php SOLSend.php SOLStatement.php SOLVariable.php XMLParser.php readme2.md

# If you have a rozsireni file, include it
if [ -f "rozsireni" ]; then
    zip "$ZIP_FILE" rozsireni
fi

echo "ZIP archive created: $ZIP_FILE"