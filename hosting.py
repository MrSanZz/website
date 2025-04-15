from http.server import BaseHTTPRequestHandler, HTTPServer
import socketserver
import socket
import threading

class ProxyHandler(BaseHTTPRequestHandler):
    def do_GET(self):
        subdomain = self.headers['Host'].split('.')[0]
        try:
            port = int(subdomain)  # Misalnya: 3000.tunnel.example.com
        except ValueError:
            self.send_error(400, "Invalid subdomain format")
            return

        try:
            with socket.create_connection(("localhost", port)) as remote_socket:
                remote_socket.sendall(f"GET {self.path} HTTP/1.0\r\nHost: localhost\r\n\r\n".encode())
                response = remote_socket.recv(4096)
                self.wfile.write(response)
        except Exception as e:
            self.send_error(502, f"Error connecting to port {port}: {e}")

def run_server():
    server_address = ('', 80)
    httpd = HTTPServer(server_address, ProxyHandler)
    print("Proxy server running on port 80...")
    httpd.serve_forever()

if __name__ == '__main__':
    threading.Thread(target=run_server).start()
