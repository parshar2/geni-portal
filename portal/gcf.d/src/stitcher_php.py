#!/usr/bin/python

#----------------------------------------------------------------------
# Copyright (c) 2012-2014 Raytheon BBN Technologies
#
# Permission is hereby granted, free of charge, to any person obtaining
# a copy of this software and/or hardware specification (the "Work") to
# deal in the Work without restriction, including without limitation the
# rights to use, copy, modify, merge, publish, distribute, sublicense,
# and/or sell copies of the Work, and to permit persons to whom the Work
# is furnished to do so, subject to the following conditions:
#
# The above copyright notice and this permission notice shall be
# included in all copies or substantial portions of the Work.
#
# THE WORK IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
# OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
# MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
# NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
# HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
# WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
# OUT OF OR IN CONNECTION WITH THE WORK OR THE USE OR OTHER DEALINGS
# IN THE WORK.
#----------------------------------------------------------------------

import string
import sys
#import gcf.oscript as omni
import stitcher
import os.path
from optparse import OptionParser
import json

def main(argv=None):
    #parser = omni.getParser()
    # Parse Options
    #(options, args) = parser.parse_args()
    
    text, obj = stitcher.call( sys.argv[1:] )

    if type(obj) == type({}):
        obj2 = {}
        for key, value in obj.items():
            obj2[str(key)]=value
    else:
        obj2 = obj
    # serialize using json
    jsonObj = json.dumps( (text, obj2), indent=4 )
    print jsonObj
        
if __name__ == "__main__":
    sys.exit(main())