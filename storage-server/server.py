#!/usr/bin/env python3
from http.server import BaseHTTPRequestHandler,ThreadingHTTPServer
from pathlib import Path
import os,secrets,json,urllib.parse,base64
ROOT=Path(os.environ.get("PP_STORAGE_ROOT","./Projects")).resolve();ROOT.mkdir(parents=True,exist_ok=True)
TOKEN=os.environ.get("PP_STORAGE_TOKEN") or secrets.token_urlsafe(32)
HOST=os.environ.get("PP_STORAGE_HOST","0.0.0.0");PORT=int(os.environ.get("PP_STORAGE_PORT","5000"))
def safe(rel):
 rel=rel.replace("\\","/").lstrip("/");p=(ROOT/rel).resolve()
 if p!=ROOT and ROOT not in p.parents:raise ValueError("invalid path")
 return p
class H(BaseHTTPRequestHandler):
 def auth(self):return secrets.compare_digest(self.headers.get("X-PP-Token",""),TOKEN)
 def out(self,c,o):
  b=json.dumps(o,ensure_ascii=False).encode();self.send_response(c);self.send_header("Content-Type","application/json; charset=utf-8");self.send_header("Content-Length",str(len(b)));self.end_headers();self.wfile.write(b)
 def do_GET(self):
  if not self.auth():return self.out(401,{"error":"unauthorized"})
  q=urllib.parse.urlparse(self.path)
  if q.path=="/health":return self.out(200,{"ok":True})
  if q.path=="/download":
   p=safe(urllib.parse.parse_qs(q.query).get("path",[""])[0])
   if not p.is_file():return self.out(404,{"error":"not found"})
   b=p.read_bytes();self.send_response(200);self.send_header("Content-Type","application/octet-stream");self.send_header("Content-Length",str(len(b)));self.end_headers();self.wfile.write(b);return
  self.out(404,{"error":"not found"})
 def do_POST(self):
  if not self.auth():return self.out(401,{"error":"unauthorized"})
  n=int(self.headers.get("Content-Length","0"));data=json.loads(self.rfile.read(n))
  try:
   if self.path=="/mkdir":safe(data["path"]).mkdir(parents=True,exist_ok=True);return self.out(200,{"ok":True})
   if self.path=="/upload":
    p=safe(data["path"]);p.parent.mkdir(parents=True,exist_ok=True);b=base64.b64decode(data["content"],validate=True);p.write_bytes(b);return self.out(200,{"ok":True,"bytes":len(b)})
   self.out(404,{"error":"not found"})
  except Exception as e:self.out(400,{"error":str(e)})
 def log_message(self,*a):print(a[0]%a[1:])
if __name__=="__main__":
 print("PP Task Storage Server");print("ROOT:",ROOT);print("URL: http://<このPCのIP>:"+str(PORT));print("TOKEN:",TOKEN);print("終了: Ctrl+C");ThreadingHTTPServer((HOST,PORT),H).serve_forever()
