import json
import sys
print(json.dumps({"teste": "ok"}, ensure_ascii=False, separators=(',', ':')))
sys.stdout.flush()