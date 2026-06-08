#!/usr/bin/env ruby

require 'csv'

if ARGV.empty?
  puts "Usage: ruby script.rb <csv_file>"
  exit 1
end

filename = ARGV[0]

o = File.open("emails","w")
CSV.foreach(filename) do |row|
  if row.size >= 10 && row[9] != "" && row[9] != "Email"
    o.puts "\"#{row[9]}\","
  else
    puts "Warning: Row too short"
  end
end
