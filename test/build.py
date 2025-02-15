#!/usr/bin/env python3.11

import os
import zipfile

current_dir = os.path.dirname(os.path.realpath(__file__))

# Define the paths for the files
src_file = os.path.join(current_dir, '..', 'src', 'parse.py')
doc_file = os.path.join(current_dir, '..', 'doc', 'dokumentace.pdf')

zip_filename = os.path.join(current_dir, 'xrepcim00.zip')

# Create a zip file and add the two files to it
with zipfile.ZipFile(zip_filename, 'w') as zipf:
    zipf.write(src_file, os.path.relpath(src_file, current_dir))
    zipf.write(doc_file, os.path.relpath(doc_file, current_dir))